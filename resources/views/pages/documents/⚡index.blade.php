<?php
use App\Models\Document;
use Livewire\Attributes\{Computed, Title};
use Livewire\{Component, WithPagination};
new #[Title('Documentos')] class extends Component {
    use WithPagination;
    public string $search = '';
    public function updatedSearch(): void { $this->resetPage(); }
    #[Computed]
    public function documents(): mixed
    {
        return Document::query()->where('user_id', auth()->id())
            ->when($this->search !== '', fn ($query) => $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower(mb_substr($this->search, 0, 160)).'%']))
            ->withCount(['signers', 'signers as signed_count' => fn ($q) => $q->whereNotNull('signed_at')])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10);
    }
    #[Computed]
    public function stats(): array
    {
        $counts = Document::where('user_id', auth()->id())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        return ['total' => $counts->sum(), 'pending' => $counts['awaiting_signatures'] ?? 0, 'accepted' => ($counts['processing'] ?? 0) + ($counts['awaiting_timestamp'] ?? 0)];
    }
}; ?>
<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><flux:text class="mb-2 uppercase tracking-widest">Seu espaço de assinaturas</flux:text><flux:heading size="xl">Documentos</flux:heading><flux:text class="mt-2">Envie, acompanhe e preserve cada aceite.</flux:text></div>
        <flux:button variant="primary" :href="route('documents.create')" wire:navigate>Novo documento</flux:button>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (['total' => 'Documentos', 'pending' => 'Aguardando assinatura', 'accepted' => 'Aceites coletados'] as $key => $label)
            <div wire:key="stat-{{ $key }}" class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700"><flux:text>{{ $label }}</flux:text><p class="mt-3 text-4xl font-semibold tracking-tight">{{ $this->stats[$key] }}</p></div>
        @endforeach
    </div>
    <flux:callout icon="information-circle" heading="Piloto local">Use documentos fictícios. O carimbo da Bry e a retenção imutável em nuvem ainda precisam ser configurados e homologados.</flux:callout>
    <flux:input wire:model.live.debounce.300ms="search" label="Buscar documento" placeholder="Digite o título…" />
    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm"><thead class="bg-zinc-50 dark:bg-zinc-900"><tr><th class="p-4">Documento</th><th class="p-4">Situação</th><th class="p-4">Assinaturas</th><th class="p-4">Criado em</th></tr></thead><tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
        @forelse ($this->documents as $document)
            <tr wire:key="{{ $document->id }}"><td class="p-4"><a class="font-medium underline decoration-zinc-300 underline-offset-4" href="{{ route('documents.show', $document) }}" wire:navigate>{{ $document->title }}</a></td><td class="p-4">{{ $document->statusLabel() }}</td><td class="p-4">{{ $document->signed_count }} / {{ $document->signers_count }}</td><td class="p-4 whitespace-nowrap">{{ $document->created_at->timezone('America/Campo_Grande')->format('d/m/Y H:i') }}</td></tr>
        @empty
            <tr><td colspan="4" class="p-14 text-center"><flux:heading>Nenhum documento por aqui</flux:heading><flux:text class="mt-2">Comece enviando um PDF e indicando quem deve assinar.</flux:text></td></tr>
        @endforelse
        </tbody></table>
    </div>
    {{ $this->documents->links() }}
</div>
