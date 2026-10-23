<x-public-shell>
<div class="space-y-7">
    <div><p class="text-sm text-zinc-500">Convite individual</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $document->title }}</h1><p class="mt-3 text-zinc-600">Olá, {{ $signer->name }}. Confirme seu e-mail para ler e assinar.</p></div>
    @if(session('status'))<div role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-red-900">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($signer->signed_at)
        <section class="rounded-2xl border border-emerald-200 bg-white p-8"><h2 class="text-xl font-semibold">Seu aceite foi registrado</h2><p class="mt-3">{{ $signer->signed_at->timezone('America/Campo_Grande')->format('d/m/Y H:i:s') }} (horário de Campo Grande).</p><p class="mt-3 text-zinc-600">Este é um teste do MVP. A finalização com carimbo da Bry ainda não está habilitada.</p></section>
    @elseif(!$verified)
        <section class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-7"><h2 class="text-xl font-semibold">1. Confirme seu e-mail</h2><p class="text-zinc-600">Enviaremos um código ao endereço cadastrado neste convite. Ele vale por 10 minutos.</p>
            <form method="POST" action="{{ route('signing.code', $signer) }}">@csrf<flux:button type="submit">Enviar código por e-mail</flux:button></form>
            <form method="POST" action="{{ route('signing.verify', $signer) }}" class="flex flex-col gap-4 sm:max-w-sm">@csrf<flux:input name="code" label="Código de 6 dígitos" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required /><flux:button type="submit" variant="primary">Confirmar código</flux:button></form>
        </section>
    @else
        <section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-7"><h2 class="text-xl font-semibold">2. Leia o documento</h2><p>Abra o PDF antes de confirmar seu aceite. O arquivo será aberto em uma nova aba.</p><flux:button :href="route('signing.pdf', $signer)" target="_blank" rel="noopener">Abrir PDF para leitura</flux:button><p class="text-xs font-medium">SHA-256 do documento</p><code class="block break-all text-xs text-zinc-600">{{ $document->sha256 }}</code></section>
        <form method="POST" action="{{ route('signing.accept', $signer) }}" class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-7">@csrf<input type="hidden" name="sha256" value="{{ $document->sha256 }}"><h2 class="text-xl font-semibold">3. Confirme sua assinatura</h2><label class="flex items-start gap-3"><input class="mt-1 size-5 shrink-0" type="checkbox" name="consent" value="1" required><span>{{ $document->consent_text }}</span></label><flux:button type="submit" variant="primary">Assinar documento de teste</flux:button></form>
    @endif
    <p class="text-sm text-zinc-500">Para registrar este aceite, armazenamos o e-mail informado, a confirmação por código, o horário, o IP e informações do navegador. Isso não equivale a uma verificação documental de identidade. Use apenas dados fictícios neste piloto.</p>
</div>
</x-public-shell>
