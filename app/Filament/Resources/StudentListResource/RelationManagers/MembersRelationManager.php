<?php

namespace App\Filament\Resources\StudentListResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    // Bewusst auf allMembers (ungefiltert) — Attach/Detach soll auch
    // soft-deleted Schueler erreichen, die noch in der Liste haengen.
    protected static string $relationship = 'allMembers';

    protected static ?string $title = 'Mitglieder';

    protected static ?string $modelLabel = 'Schüler';

    protected static ?string $pluralModelLabel = 'Schüler';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_number')
                    ->label('Kassenzeichen')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),

                Tables\Columns\IconColumn::make('deleted_at')
                    ->label('Gelöscht')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box-x-mark')
                    ->trueColor('danger')
                    ->falseIcon('')
                    ->getStateUsing(fn ($record) => $record->trashed()),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Schüler hinzufügen')
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }
}
