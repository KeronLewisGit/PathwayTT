<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /** is_admin and email_verified_at are guarded; set them explicitly. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $isAdmin = (bool) ($data['is_admin'] ?? false);
        $verifiedAt = $data['email_verified_at'] ?? null;
        unset($data['is_admin'], $data['email_verified_at']);

        $record->fill($data)
            ->forceFill(['is_admin' => $isAdmin, 'email_verified_at' => $verifiedAt])
            ->save();

        return $record;
    }
}
