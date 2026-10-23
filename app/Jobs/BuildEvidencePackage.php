<?php

namespace App\Jobs;

use App\Actions\Signing\BuildEvidence;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildEvidencePackage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public string $documentId) {}

    public function handle(BuildEvidence $builder): void
    {
        $builder->handle($this->documentId);
    }
}
