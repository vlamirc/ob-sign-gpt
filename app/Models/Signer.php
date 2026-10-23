<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SignerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $otp_expires_at
 * @property CarbonImmutable|null $otp_sent_at
 * @property CarbonImmutable|null $grant_expires_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $viewed_at
 * @property CarbonImmutable|null $signed_at
 */
class Signer extends Model
{
    /** @use HasFactory<SignerFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'email'];

    protected $hidden = ['token_hash', 'otp_hash', 'grant_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['otp_expires_at' => 'immutable_datetime', 'otp_sent_at' => 'immutable_datetime', 'grant_expires_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'viewed_at' => 'immutable_datetime', 'signed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
