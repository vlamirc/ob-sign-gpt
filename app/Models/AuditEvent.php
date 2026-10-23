<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['signer_id', 'type', 'payload', 'previous_hash', 'hash', 'recorded_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Eventos não podem ser alterados.'));
        static::deleting(fn () => throw new LogicException('Eventos não podem ser excluídos.'));
    }

    /**
     * Caller must hold the document row lock inside a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(Document $document, string $type, array $data = [], ?Signer $signer = null): self
    {
        $previous = $document->events()->reorder()->latest('id')->value('hash');
        $payload = json_encode([
            'document_id' => $document->id, 'document_sha256' => $document->sha256,
            'signer_id' => $signer?->id, 'type' => $type,
            'at' => now()->utc()->format('Y-m-d\\TH:i:s.u\\Z'), 'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $document->events()->create([
            'signer_id' => $signer?->id, 'type' => $type, 'payload' => $payload,
            'previous_hash' => $previous, 'hash' => hash('sha256', ($previous ?? '').$payload), 'recorded_at' => now(),
        ]);
    }
}
