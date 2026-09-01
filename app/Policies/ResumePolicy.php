<?php

namespace App\Policies;

use App\Models\Resume;
use App\Models\User;

class ResumePolicy
{
    public function view(User $user, Resume $resume): bool
    {
        return $resume->user_id === $user->id;
    }

    public function delete(User $user, Resume $resume): bool
    {
        return $resume->user_id === $user->id;
    }
}
