<?php

namespace App\Http\Controllers;

use App\Actions\Signing\SigningFlow;
use App\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    public function original(Document $document, SigningFlow $flow): Response
    {
        Gate::authorize('view', $document);

        return response($flow->assertIntegrity($document), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="original.pdf"']);
    }

    public function evidence(Document $document): Response
    {
        Gate::authorize('view', $document);
        abort_unless($document->evidence_path !== null, 404);
        $bytes = Storage::disk($document->disk)->get($document->evidence_path);
        abort_unless(is_string($bytes) && hash_equals($document->evidence_sha256, hash('sha256', $bytes)), 409);

        return response($bytes, 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="evidencias-'.$document->id.'.zip"']);
    }
}
