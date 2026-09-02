<?php

namespace App\Filament\Resources\LearningResourceResource\Pages;

use App\Filament\Resources\LearningResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLearningResource extends EditRecord
{
    protected static string $resource = LearningResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
