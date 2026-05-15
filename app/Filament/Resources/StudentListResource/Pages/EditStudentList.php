<?php

namespace App\Filament\Resources\StudentListResource\Pages;

use App\Filament\Resources\StudentListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudentList extends EditRecord
{
    protected static string $resource = StudentListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            // Bewusst KEIN ForceDeleteAction — Audit-Pivots schuetzen.
        ];
    }
}
