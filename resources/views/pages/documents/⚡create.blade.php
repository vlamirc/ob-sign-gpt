<?php
use App\Actions\Signing\CreateDocument;
use Livewire\Attributes\Title;
use Livewire\{Component, WithFileUploads};
new #[Title('Novo documento')] class extends Component {
    use WithFileUploads;
    public string $title = '';
    public $pdf;
    public array $signers = [['name' => '', 'email' => '']];
    public function addSigner(): void { if (count($this->signers) < 10) { $this->signers[] = ['name' => '', 'email' => '']; } }
    public function removeSigner(int $index): void { if (count($this->signers) > 1) { unset($this->signers[$index]); $this->signers = array_values($this->signers); } }
    public function save(CreateDocument $create): void
    {
        abort_unless(auth()->check(), 403);
        $this->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240']]);
        $document = $create->handle(auth()->user(), $this->title, $this->pdf, $this->signers);
        $this->redirectRoute('documents.show', $document, navigate: true);
    }
}; ?>
<div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
    <div><flux:button variant="ghost" :href="route('dashboard')" wire:navigate>← Documentos</flux:button><flux:heading size="xl" class="mt-4">Prepare uma assinatura</flux:heading><flux:text class="mt-2">O PDF será preservado exatamente como foi enviado.</flux:text></div>
    <form wire:submit="save" class="flex flex-col gap-8">
        <section class="space-y-5 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">1. Documento</flux:heading>
            <flux:input label="Título" wire:model="title" placeholder="Ex.: Contrato de prestação de serviços" maxlength="160" required />
            <flux:input type="file" label="Arquivo PDF · até 10 MB" wire:model="pdf" accept="application/pdf,.pdf" required />
            <flux:error name="file" />
            <p wire:loading wire:target="pdf" class="text-sm">Enviando arquivo…</p>
        </section>
        <section class="space-y-5 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">2. Quem vai assinar?</flux:heading>
            <flux:text>Todos receberão convites individuais. Confira os endereços antes de enviar.</flux:text>
            @foreach ($signers as $index => $signer)
                <div wire:key="signer-{{ $index }}" class="grid gap-4 rounded-xl bg-zinc-50 p-4 sm:grid-cols-2 dark:bg-zinc-900">
                    <flux:input label="Nome completo" wire:model="signers.{{ $index }}.name" required maxlength="160" />
                    <flux:input label="E-mail" type="email" wire:model="signers.{{ $index }}.email" required />
                    @if (count($signers) > 1)<flux:button type="button" size="sm" variant="ghost" wire:click="removeSigner({{ $index }})">Remover</flux:button>@endif
                </div>
            @endforeach
            <flux:error name="signers" />
            @if (count($signers) < 10)<flux:button type="button" wire:click="addSigner">Adicionar signatário</flux:button>@endif
        </section>
        <div class="flex flex-wrap items-center justify-between gap-4"><flux:text>Você revisará tudo antes de enviar os convites.</flux:text><flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save,pdf">Salvar rascunho</flux:button></div>
    </form>
</div>
