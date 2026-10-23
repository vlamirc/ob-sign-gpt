<?php

namespace App\Http\Controllers;

use App\Actions\Signing\SigningFlow;
use App\Models\Signer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SigningController extends Controller
{
    public function __construct(private SigningFlow $flow) {}

    public function invite(Request $request, string $token): RedirectResponse
    {
        $signer = Signer::where('token_hash', hash('sha256', $token))->firstOrFail();
        $this->flow->assertOpen($signer->document);
        $request->session()->regenerate();
        $request->session()->put('invitation.'.$signer->id, $signer->token_hash);

        return redirect()->route('signing.show', $signer);
    }

    public function show(Request $request, Signer $signer): View
    {
        $this->authorizeInvitation($request, $signer);

        return view('signing.show', ['signer' => $signer, 'document' => $signer->document,
            'verified' => $this->flow->canRead($signer, $request->session()->get('grant.'.$signer->id))]);
    }

    public function code(Request $request, Signer $signer): RedirectResponse
    {
        $this->authorizeInvitation($request, $signer);
        $this->flow->requestCode($signer);
        $request->session()->forget('grant.'.$signer->id);

        return back()->with('status', 'Código enviado. Confira seu e-mail.');
    }

    public function verify(Request $request, Signer $signer): RedirectResponse
    {
        $this->authorizeInvitation($request, $signer);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $grant = $this->flow->verifyCode($signer, $data['code'], $this->context($request));
        $request->session()->regenerate();
        $request->session()->put('grant.'.$signer->id, $grant);

        return redirect()->route('signing.show', $signer)->with('status', 'E-mail confirmado. Leia o documento antes de assinar.');
    }

    public function pdf(Request $request, Signer $signer): Response
    {
        $this->authorizeInvitation($request, $signer);
        $bytes = $this->flow->viewDocument($signer, (string) $request->session()->get('grant.'.$signer->id), $this->context($request));

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="documento.pdf"', 'Content-Security-Policy' => "sandbox; frame-ancestors 'self'"]);
    }

    public function accept(Request $request, Signer $signer): RedirectResponse
    {
        $this->authorizeInvitation($request, $signer);
        $data = $request->validate(['consent' => ['accepted'], 'sha256' => ['required', 'string', 'size:64']]);
        $this->flow->accept($signer, (string) $request->session()->get('grant.'.$signer->id), $data['sha256'], $this->context($request));

        return redirect()->route('signing.show', $signer)->with('status', 'Seu aceite foi registrado.');
    }

    private function authorizeInvitation(Request $request, Signer $signer): void
    {
        $this->flow->assertPilot();
        $capability = $request->session()->get('invitation.'.$signer->id);
        abort_unless(is_string($capability) && is_string($signer->token_hash) && hash_equals($signer->token_hash, $capability), 404);
        abort_if($signer->document->status === 'cancelled' || ! $signer->document->expires_at?->gt(now()), 410);
    }

    /** @return array{ip: string|null, user_agent: string} */
    private function context(Request $request): array
    {
        return ['ip' => $request->ip(), 'user_agent' => mb_substr($request->userAgent() ?? '', 0, 512)];
    }
}
