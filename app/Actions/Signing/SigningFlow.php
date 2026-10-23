<?php

namespace App\Actions\Signing;

use App\Jobs\BuildEvidencePackage;
use App\Mail\SigningCode;
use App\Mail\SigningInvitation;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Signer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SigningFlow
{
    public function send(Document $document): void
    {
        Gate::authorize('update', $document);
        $this->assertPilot();
        DB::transaction(function () use ($document): void {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($document->status === 'draft', 409, 'Documento já enviado.');
            $this->assertIntegrity($document);
            abort_unless($document->signers()->exists(), 422);
            $document->status = 'awaiting_signatures';
            $document->consent_text = config('signing.consent_text');
            $document->consent_version = config('signing.consent_version');
            $document->sent_at = now();
            $document->expires_at = now()->addDays(config('signing.invitation_days'));
            $document->save();
            foreach ($document->signers as $signer) {
                $token = Str::random(64);
                $signer->token_hash = hash('sha256', $token);
                $signer->save();
                Mail::to($signer->email)->queue((new SigningInvitation($signer->name, $document->title, route('signing.invite', $token)))->afterCommit());
            }
            AuditEvent::record($document, 'document.sent');
        });
    }

    public function cancel(Document $document): void
    {
        Gate::authorize('update', $document);
        DB::transaction(function () use ($document): void {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless(in_array($document->status, ['draft', 'awaiting_signatures'], true), 409);
            $document->status = 'cancelled';
            $document->save();
            $document->signers()->update(['token_hash' => null, 'otp_hash' => null, 'grant_hash' => null]);
            AuditEvent::record($document, 'document.cancelled');
        });
    }

    public function requestCode(Signer $signer): void
    {
        $this->assertPilot();
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($signer, $code): void {
            $document = Document::query()->lockForUpdate()->findOrFail($signer->document_id);
            $this->assertOpen($document);
            $signer = Signer::query()->lockForUpdate()->findOrFail($signer->id);
            abort_if($signer->signed_at !== null, 409);
            if ($signer->otp_sent_at?->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['code' => 'Aguarde um minuto para solicitar outro código.']);
            }
            $signer->otp_hash = Hash::make($code);
            $signer->otp_expires_at = now()->addMinutes(config('signing.otp_minutes'));
            $signer->otp_sent_at = now();
            $signer->otp_attempts = 0;
            $signer->grant_hash = null;
            $signer->grant_expires_at = null;
            $signer->viewed_at = null;
            $signer->save();
            AuditEvent::record($document, 'otp.requested', [], $signer);
        });
        // Synchronous delivery avoids storing a short-lived OTP in a queued payload.
        Mail::to($signer->email)->send(new SigningCode($code));
    }

    /** @param array{ip: string|null, user_agent: string} $context */
    public function verifyCode(Signer $signer, string $code, array $context): string
    {
        $this->assertPilot();
        $grant = DB::transaction(function () use ($signer, $code, $context): ?string {
            $document = Document::query()->lockForUpdate()->findOrFail($signer->document_id);
            $this->assertOpen($document);
            $signer = Signer::query()->lockForUpdate()->findOrFail($signer->id);
            abort_if($signer->signed_at !== null, 409);
            if (! $signer->otp_hash || ! $signer->otp_expires_at?->gt(now()) || $signer->otp_attempts >= config('signing.max_otp_attempts')) {
                return null;
            }
            $signer->otp_attempts++;
            if (! Hash::check($code, $signer->otp_hash)) {
                $signer->save();
                AuditEvent::record($document, 'otp.failed', $context, $signer);

                return null;
            }
            $grant = Str::random(64);
            $signer->otp_hash = null;
            $signer->grant_hash = hash('sha256', $grant);
            $signer->grant_expires_at = now()->addMinutes(config('signing.grant_minutes'));
            $signer->verified_at = now();
            $signer->save();
            AuditEvent::record($document, 'email.verified', $context, $signer);

            return $grant;
        });
        // Throw outside the transaction so failed-attempt increments are committed.
        if ($grant === null) {
            throw ValidationException::withMessages(['code' => 'Código inválido, expirado ou bloqueado. Solicite um novo código.']);
        }

        return $grant;
    }

    public function canRead(Signer $signer, ?string $grant): bool
    {
        return is_string($grant) && $grant !== '' && $signer->grant_hash !== null
            && $signer->grant_expires_at?->gt(now())
            && hash_equals($signer->grant_hash, hash('sha256', $grant));
    }

    /** @param array{ip: string|null, user_agent: string} $context */
    public function viewDocument(Signer $signer, string $grant, array $context): string
    {
        return DB::transaction(function () use ($signer, $grant, $context): string {
            $document = Document::query()->lockForUpdate()->findOrFail($signer->document_id);
            $this->assertOpen($document);
            $signer = Signer::query()->lockForUpdate()->findOrFail($signer->id);
            abort_unless($this->canRead($signer, $grant), 403);
            $bytes = $this->assertIntegrity($document);
            if (! $signer->viewed_at) {
                $signer->viewed_at = now();
                $signer->save();
                AuditEvent::record($document, 'document.presented', $context, $signer);
            }

            return $bytes;
        });
    }

    /** @param array{ip: string|null, user_agent: string} $context */
    public function accept(Signer $signer, string $grant, string $expectedHash, array $context): void
    {
        $this->assertPilot();
        DB::transaction(function () use ($signer, $grant, $expectedHash, $context): void {
            $document = Document::query()->lockForUpdate()->findOrFail($signer->document_id);
            $signer = Signer::query()->lockForUpdate()->findOrFail($signer->id);
            abort_unless($this->canRead($signer, $grant), 403);
            if ($signer->signed_at) {
                return;
            }
            $this->assertOpen($document);
            abort_unless($signer->viewed_at !== null, 422, 'Abra o PDF antes de assinar.');
            abort_unless(hash_equals($document->sha256, $expectedHash), 409, 'Versão divergente.');
            $this->assertIntegrity($document);
            $signer->signed_at = now();
            $signer->save();
            AuditEvent::record($document, 'signature.accepted', array_merge($context, [
                'signer_name' => $signer->name, 'signer_email' => $signer->email,
                'authentication' => 'email_otp', 'consent_version' => $document->consent_version,
                'consent_text' => $document->consent_text, 'email_verified_at' => $signer->verified_at?->toIso8601String(),
            ]), $signer);
            if (! $document->signers()->whereNull('signed_at')->exists()) {
                $document->status = 'processing';
                $document->accepted_at = now();
                $document->save();
                AuditEvent::record($document, 'document.all_accepted');
                BuildEvidencePackage::dispatch($document->id)->afterCommit();
            }
        });
    }

    public function assertIntegrity(Document $document): string
    {
        $bytes = Storage::disk($document->disk)->get($document->path);
        abort_unless(is_string($bytes) && hash_equals($document->sha256, hash('sha256', $bytes)), 409, 'Falha na integridade do documento.');

        return $bytes;
    }

    public function assertOpen(Document $document): void
    {
        abort_unless($document->status === 'awaiting_signatures' && $document->expires_at?->gt(now()), 410, 'Convite encerrado ou expirado.');
    }

    public function assertPilot(): void
    {
        abort_if(app()->isProduction(), 503, 'MVP ainda não liberado para assinaturas em produção.');
    }
}
