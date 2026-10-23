<?php

namespace Tests\Feature;

use App\Actions\Signing\BuildEvidence;
use App\Actions\Signing\SigningFlow;
use App\Jobs\BuildEvidencePackage;
use App\Mail\SigningCode;
use App\Mail\SigningInvitation;
use App\Models\Document;
use App\Models\Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SigningFlowTest extends TestCase
{
    use RefreshDatabase;

    private function invite(): array
    {
        Storage::fake('local');
        Mail::fake();
        $document = Document::factory()->create();
        Storage::disk('local')->put($document->path, '%PDF-1.4 teste');
        $signer = Signer::factory()->for($document)->create();
        $this->actingAs($document->user);
        app(SigningFlow::class)->send($document);
        Mail::assertQueued(SigningInvitation::class, fn ($mail) => $mail->hasTo($signer->email));
        $mail = Mail::queued(SigningInvitation::class)->first();
        $this->post(route('logout'));
        $this->get($mail->url)->assertRedirect(route('signing.show', $signer));

        return [$document->fresh(), $signer->fresh()];
    }

    private function verify(Signer $signer): void
    {
        $this->post(route('signing.code', $signer))->assertRedirect();
        Mail::assertSent(SigningCode::class, fn ($mail) => $mail->hasTo($signer->email));
        $code = Mail::sent(SigningCode::class)->last()->code;
        $this->post(route('signing.verify', $signer), ['code' => $code])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_complete_flow_preserves_original_and_builds_an_idempotent_unsealed_package(): void
    {
        [$document, $signer] = $this->invite();
        config(['signing.consent_text' => 'Novo texto que não deve alterar convites existentes']);
        Queue::fake([BuildEvidencePackage::class]);
        $this->get(route('signing.pdf', $signer))->assertForbidden();
        $this->verify($signer);
        $this->get(route('signing.show', $signer))->assertOk()->assertSee('Leia o documento')->assertSee($document->consent_text)->assertDontSee('Novo texto que');
        $this->get(route('signing.pdf', $signer))->assertOk()->assertContent('%PDF-1.4 teste')->assertHeader('Referrer-Policy', 'no-referrer');
        $payload = ['consent' => '1', 'sha256' => $document->sha256];
        $this->post(route('signing.accept', $signer), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('signing.accept', $signer), $payload)->assertRedirect();
        $this->assertNotNull($signer->fresh()->signed_at);
        $this->assertSame(1, $document->events()->where('type', 'signature.accepted')->count());
        Queue::assertPushed(BuildEvidencePackage::class, 1);
        $builder = app(BuildEvidence::class);
        $builder->handle($document->id);
        $firstHash = $document->fresh()->evidence_sha256;
        $builder->handle($document->id);
        $this->assertSame($firstHash, $document->fresh()->evidence_sha256);
        $this->assertSame('awaiting_timestamp', $document->fresh()->status);
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($document->fresh()->evidence_path));
        $this->assertSame('%PDF-1.4 teste', $zip->getFromName('original.pdf'));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame('not_requested', $manifest['timestamp']['status']);
        $this->assertSame($document->sha256, $manifest['original_sha256']);
        $this->assertSame(hash('sha256', $zip->getFromName('relatorio.pdf')), $manifest['report_sha256']);
        $zip->close();
    }

    public function test_otp_is_hashed_expires_and_cannot_be_reused(): void
    {
        $this->freezeTime();
        [, $signer] = $this->invite();
        $this->post(route('signing.code', $signer));
        $code = Mail::sent(SigningCode::class)->last()->code;
        $this->assertNotSame($code, $signer->fresh()->otp_hash);
        $this->travel(11)->minutes();
        $this->post(route('signing.verify', $signer), ['code' => $code])->assertSessionHasErrors('code');
        $this->verify($signer);
        $newCode = Mail::sent(SigningCode::class)->last()->code;
        $this->post(route('signing.verify', $signer), ['code' => $newCode])->assertSessionHasErrors('code');
    }

    public function test_failed_attempts_are_persisted_and_lock_the_code(): void
    {
        [, $signer] = $this->invite();
        $this->post(route('signing.code', $signer));
        $code = Mail::sent(SigningCode::class)->last()->code;
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('signing.verify', $signer), ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->assertSame(5, $signer->fresh()->otp_attempts);
        $this->post(route('signing.verify', $signer), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull($signer->fresh()->verified_at);
    }

    public function test_acceptance_requires_document_presentation_consent_and_matching_bytes(): void
    {
        [$document, $signer] = $this->invite();
        $this->verify($signer);
        $this->post(route('signing.accept', $signer), ['sha256' => $document->sha256])->assertSessionHasErrors('consent');
        $this->post(route('signing.accept', $signer), ['consent' => 1, 'sha256' => $document->sha256])->assertUnprocessable();
        $this->get(route('signing.pdf', $signer))->assertOk();
        $this->post(route('signing.accept', $signer), ['consent' => 1, 'sha256' => str_repeat('a', 64)])->assertConflict();
        Storage::disk('local')->put($document->path, '%PDF-1.4 altered');
        $this->post(route('signing.accept', $signer), ['consent' => 1, 'sha256' => $document->sha256])->assertConflict();
        $this->assertNull($signer->fresh()->signed_at);
    }

    public function test_cancellation_revokes_an_already_open_invitation(): void
    {
        [$document, $signer] = $this->invite();
        $this->verify($signer);
        $this->actingAs($document->user);
        app(SigningFlow::class)->cancel($document);
        $this->get(route('signing.show', $signer))->assertNotFound();
        $this->post(route('signing.accept', $signer), ['consent' => 1, 'sha256' => $document->sha256])->assertNotFound();
        $this->assertNull($signer->fresh()->signed_at);
    }

    public function test_other_signer_cannot_reuse_the_invitation_session(): void
    {
        [$document] = $this->invite();
        $other = Signer::factory()->for($document)->create();
        $this->get(route('signing.show', $other))->assertNotFound();
        $this->post(route('signing.code', $other))->assertNotFound();
    }

    public function test_expired_invitation_and_expired_grant_are_rejected(): void
    {
        $this->freezeTime();
        [$document, $signer] = $this->invite();
        $this->verify($signer);
        $this->travel(31)->minutes();
        $this->get(route('signing.pdf', $signer))->assertForbidden();
        $this->travel(8)->days();
        $this->get(route('signing.show', $signer))->assertGone();
    }

    public function test_resending_code_invalidates_old_code_and_is_throttled(): void
    {
        $this->freezeTime();
        [, $signer] = $this->invite();
        $this->post(route('signing.code', $signer));
        $old = Mail::sent(SigningCode::class)->last()->code;
        $this->post(route('signing.code', $signer))->assertSessionHasErrors('code');
        $this->travel(61)->seconds();
        $this->post(route('signing.code', $signer))->assertRedirect();
        $current = Mail::sent(SigningCode::class)->last()->code;
        if ($old !== $current) {
            $this->post(route('signing.verify', $signer), ['code' => $old])->assertSessionHasErrors('code');
        }
        $this->post(route('signing.verify', $signer), ['code' => $current])->assertSessionHasNoErrors();
    }

    public function test_no_package_until_every_signer_accepts(): void
    {
        [$document, $signer] = $this->invite();
        Signer::factory()->for($document)->create();
        Queue::fake([BuildEvidencePackage::class]);
        $this->verify($signer);
        $this->get(route('signing.pdf', $signer));
        $this->post(route('signing.accept', $signer), ['consent' => 1, 'sha256' => $document->sha256])->assertRedirect();
        $this->assertSame('awaiting_signatures', $document->fresh()->status);
        Queue::assertNotPushed(BuildEvidencePackage::class);
    }
}
