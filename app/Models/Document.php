<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['title', 'original_name', 'disk', 'path', 'sha256', 'size'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Signer, $this> */
    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class);
    }

    /** @return HasMany<AuditEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AuditEvent::class)->orderBy('id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Rascunho', 'awaiting_signatures' => 'Aguardando assinaturas',
            'processing' => 'Preparando evidências', 'awaiting_timestamp' => 'Aceites coletados · carimbo pendente',
            'cancelled' => 'Cancelado', default => 'Revisão necessária',
        };
    }
}
