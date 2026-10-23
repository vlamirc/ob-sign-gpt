<?php
use App\Actions\Signing\SigningFlow;
use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\{Locked, Computed, Title};
use Livewire\Component;
new #[Title('Detalhes do documento')] class extends Component {
    #[Locked] public string $documentId;
    public function mount(Document $document): void { Gate::authorize('view', $document); $this->documentId = $document->id; }
    #[Computed]
    public function document(): Document {
        $document = Document::with(['signers', 'events'])->findOrFail($this->documentId);
        Gate::authorize('view', $document);
        return $document;
    }
    public function send(SigningFlow $flow): void { $flow->send($this->document); unset($this->document); session()->flash('status', 'Convites colocados na fila de envio.'); }
    public function cancel(SigningFlow $flow): void { $flow->cancel($this->document); unset($this->document); session()->flash('status', 'Documento cancelado. Os links foram revogados.'); }
}; ?>
<div class="mx-auto flex w-full max-w-5xl flex-col gap-7" wire:poll.15s>
    <div><flux:button variant="ghost" :href="route('dashboard')" wire:navigate>← Documentos</flux:button><flux:heading size="xl" class="mt-4">{{ $this->document->title }}</flux:heading><flux:text class="mt-2">{{ $this->document->statusLabel() }}</flux:text></div>
    @if (session('status'))<flux:callout heading="Atualização">{{ session('status') }}</flux:callout>@endif
    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('documents.original', $this->document)">Baixar PDF original</flux:button>
        @if ($this->document->evidence_path)<flux:button :href="route('documents.evidence', $this->document)">Baixar evidências de teste</flux:button>@endif
        @if ($this->document->status === 'draft')<flux:button variant="primary" wire:click="send" wire:confirm="Enviar convites aos signatários informados?">Enviar para assinatura</flux:button>@endif
        @if (in_array($this->document->status, ['draft', 'awaiting_signatures']))<flux:button variant="danger" wire:click="cancel" wire:confirm="Cancelar este documento e revogar todos os convites?">Cancelar documento</flux:button>@endif
    </div>
    @if ($this->document->status === 'awaiting_timestamp')<flux:callout heading="Aceites coletados; carimbo ainda pendente">O pacote de teste contém o PDF original, relatório e manifesto de evidências. Nenhum carimbo ICP-Brasil foi emitido.</flux:callout>@endif
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="space-y-4 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700"><flux:heading size="lg">Signatários</flux:heading>
            @foreach ($this->document->signers as $signer)<div wire:key="{{ $signer->id }}" class="border-b border-zinc-100 py-3 last:border-0 dark:border-zinc-700"><p class="font-medium">{{ $signer->name }}</p><flux:text>{{ $signer->email }}</flux:text><p class="mt-2 text-sm">{{ $signer->signed_at ? 'Aceite registrado em '.$signer->signed_at->timezone('America/Campo_Grande')->format('d/m/Y H:i') : 'Aguardando aceite' }}</p></div>@endforeach
        </section>
        <section class="space-y-4 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700"><flux:heading size="lg">Identificação do arquivo</flux:heading><flux:text>{{ $this->document->original_name }} · {{ number_format($this->document->size / 1024, 0, ',', '.') }} KB</flux:text><p class="text-xs uppercase tracking-widest">SHA-256 do original</p><code class="block break-all text-xs">{{ $this->document->sha256 }}</code><flux:text>Qualquer alteração nos bytes do PDF será detectada antes da assinatura ou do download.</flux:text></section>
    </div>
    <section class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700"><flux:heading size="lg">Histórico</flux:heading><ol class="mt-4 space-y-3">
    @foreach ($this->document->events as $event)<li wire:key="event-{{ $event->id }}" class="flex flex-wrap justify-between gap-2 border-b border-zinc-100 py-2 text-sm dark:border-zinc-700"><span>{{ ['document.created'=>'Documento criado','document.sent'=>'Convites enfileirados','otp.requested'=>'Código solicitado','otp.failed'=>'Tentativa de código inválido','email.verified'=>'E-mail confirmado','document.presented'=>'PDF disponibilizado ao signatário','signature.accepted'=>'Aceite registrado','document.all_accepted'=>'Todos os aceites coletados','document.cancelled'=>'Documento cancelado'][$event->type] ?? $event->type }}</span><time>{{ $event->recorded_at->timezone('America/Campo_Grande')->format('d/m/Y H:i:s') }}</time></li>@endforeach
    </ol></section>
</div>
