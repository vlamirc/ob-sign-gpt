<?php

namespace App\Actions\Signing;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CreateDocument
{
    /** @param list<array{name?: string, email?: string}> $signers */
    public function handle(User $user, string $title, UploadedFile $file, array $signers): Document
    {
        $signers = array_map(fn (array $s): array => ['name' => trim($s['name'] ?? ''), 'email' => mb_strtolower(trim($s['email'] ?? ''))], $signers);
        Validator::make(compact('title', 'file', 'signers'), [
            'title' => ['required', 'string', 'max:160'], 'file' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'],
            'signers' => ['required', 'array', 'min:1', 'max:10'], 'signers.*.name' => ['required', 'string', 'max:160'],
            'signers.*.email' => ['required', 'email:rfc', 'max:254', 'distinct'],
        ])->validate();
        $disk = config('signing.disk');
        $path = $file->store('documents/'.$user->id, $disk);
        throw_unless(is_string($path), new \RuntimeException('Falha ao armazenar o PDF.'));
        try {
            return DB::transaction(function () use ($user, $title, $file, $signers, $disk, $path): Document {
                $document = new Document(['title' => $title, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'disk' => $disk, 'path' => $path, 'sha256' => hash_file('sha256', $file->getRealPath()), 'size' => $file->getSize()]);
                $document->user_id = $user->id;
                $document->status = 'draft';
                $document->save();
                $document->signers()->createMany($signers);
                AuditEvent::record($document, 'document.created', ['owner_id' => $user->id]);

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }
}
