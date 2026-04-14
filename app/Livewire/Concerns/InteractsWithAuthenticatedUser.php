<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait InteractsWithAuthenticatedUser
{
    protected function currentUser(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(! $user, 403);

        return $user;
    }

    protected function currentUserId(): int
    {
        return (int) $this->currentUser()->getKey();
    }
}
