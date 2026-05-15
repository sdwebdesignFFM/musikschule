<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentListResource\Pages;
use App\Filament\Resources\StudentListResource\RelationManagers;
use App\Models\Student;
use App\Models\StudentList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentListResource extends Resource
{
    protected static ?string $model = StudentList::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Listen';

    protected static ?string $pluralModelLabel = 'Listen';

    protected static ?string $modelLabel = 'Liste';

    protected static ?string $navigationGroup = 'Kampagnen';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stammdaten')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('z. B. Klavierschüler 2026'),

                        Forms\Components\Textarea::make('description')
                            ->label('Beschreibung (optional)')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),

                Forms\Components\Section::make('Hinweis')
                    ->schema([
                        Forms\Components\Placeholder::make('snapshot_info')
                            ->label('')
                            ->content(fn (?StudentList $record) => $record && $record->campaigns()->count() > 0
                                ? 'Diese Liste wird in ' . $record->campaigns()->count() . ' Kampagnen als Quelle verwendet. Änderungen an den Mitgliedern wirken sich NICHT auf bestehende Kampagnen aus (Snapshot beim Versand).'
                                : 'Beim Hinzufügen dieser Liste zu einer Kampagne werden die aktuellen Mitglieder als Empfänger eingefroren. Spätere Änderungen wirken sich nur auf neue Kampagnen aus.'),
                    ])
                    ->visible(fn (string $operation) => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount([
                    'members as members_count' => fn (Builder $q) => $q->whereNull('students.deleted_at'),
                    'campaigns as campaigns_count' => fn (Builder $q) => $q->whereNull('campaigns.deleted_at'),
                ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('members_count')
                    ->label('Schüler')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('campaigns_count')
                    ->label('In Kampagnen')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Angelegt am')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription(fn (StudentList $record) => $record->campaigns_count > 0
                        ? "Diese Liste wird in {$record->campaigns_count} Kampagnen als Quelle verwendet. Audit-Trail bleibt erhalten, bestehende Empfänger sind nicht betroffen. Trotzdem löschen?"
                        : 'Liste wird gelöscht (wiederherstellbar über Filter „Gelöscht").'
                    ),
                Tables\Actions\RestoreAction::make(),
                // Bewusst KEIN ForceDeleteAction — wuerde Audit-Pivots zerstoeren.
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    // Bewusst KEIN ForceDeleteBulkAction.
                ]),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('Noch keine Listen vorhanden')
            ->emptyStateDescription('Listen bündeln wiederkehrende Schülergruppen und können als Quelle für mehrere Kampagnen verwendet werden.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Erste Liste anlegen'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentLists::route('/'),
            'create' => Pages\CreateStudentList::route('/create'),
            'edit' => Pages\EditStudentList::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            \Illuminate\Database\Eloquent\SoftDeletingScope::class,
        ]);
    }
}
