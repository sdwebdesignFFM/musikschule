<?php

namespace App\Filament\Resources\StudentListResource\Pages;

use App\Filament\Resources\StudentListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudentLists extends ListRecords
{
    protected static string $resource = StudentListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Liste anlegen'),
        ];
    }
}
