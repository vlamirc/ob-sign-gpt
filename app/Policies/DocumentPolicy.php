<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    public function view(User $user, Document $document): Response
    {
        return $user->id === $document->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Document $document): Response
    {
        return $this->view($user, $document);
    }
}
