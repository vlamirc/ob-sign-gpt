<?php

namespace App\Actions\Signing;

use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BuildEvidence
{
    public function __construct(private SigningFlow $flow) {}

    public function handle(string $documentId): void
    {
        DB::transaction(function () use ($documentId): void {
            $document = Document::query()->lockForUpdate()->findOrFail($documentId);
            if ($document->evidence_path) {
                return;
            }
            if ($document->status !== 'processing') {
                return;
            }
            $document->load(['signers', 'events']);
            $original = $this->flow->assertIntegrity($document);
            $previous = null;
            foreach ($document->events as $event) {
                if ($event->previous_hash !== $previous || ! hash_equals($event->hash, hash('sha256', ($previous ?? '').$event->payload))) {
                    throw new RuntimeException('Cadeia de evidências inconsistente.');
                }
                $previous = $event->hash;
            }
            $report = Pdf::loadView('evidence.report', compact('document'))->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();
            $manifest = json_encode([
                'schema' => 'assina.evidence.v1', 'document_id' => $document->id,
                'title' => $document->title, 'original_sha256' => $document->sha256,
                'report_sha256' => hash('sha256', $report), 'timestamp' => ['status' => 'not_requested', 'reason' => 'ACT integration pending'],
                'environment' => 'pilot', 'audit_head' => $previous,
                'events' => $document->events->map(fn ($e): array => ['payload' => $e->payload, 'previous_hash' => $e->previous_hash, 'hash' => $e->hash])->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $temporary = tempnam(sys_get_temp_dir(), 'assina-');
            if ($temporary === false) {
                throw new RuntimeException('Falha ao criar arquivo temporário.');
            }
            try {
                $zip = new ZipArchive;
                if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Falha ao criar pacote.');
                }
                $zip->addFromString('original.pdf', $original);
                $zip->addFromString('relatorio.pdf', $report);
                $zip->addFromString('manifest.json', $manifest);
                $zip->close();
                $bytes = file_get_contents($temporary);
                if ($bytes === false) {
                    throw new RuntimeException('Falha ao ler pacote de evidências.');
                }
                $path = 'evidence/'.$document->id.'/'.hash('sha256', $bytes).'.zip';
                if (! Storage::disk($document->disk)->put($path, $bytes)) {
                    throw new RuntimeException('Falha ao preservar evidências.');
                }
                $document->evidence_path = $path;
                $document->evidence_sha256 = hash('sha256', $bytes);
                $document->status = 'awaiting_timestamp';
                $document->save();
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        });
    }
}
