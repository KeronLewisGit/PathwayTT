<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** is_admin and email_verified_at are guarded; set them explicitly. */
    protected function handleRecordCreation(array $data): Model
    {
        $isAdmin = (bool) ($data['is_admin'] ?? false);
        $verifiedAt = $data['email_verified_at'] ?? null;
        unset($data['is_admin'], $data['email_verified_at']);

        $user = User::query()->create($data);
        $user->forceFill(['is_admin' => $isAdmin, 'email_verified_at' => $verifiedAt])->save();

        return $user;
    }
}
