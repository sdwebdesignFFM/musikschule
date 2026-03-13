<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'E-Mail-Vorlagen';

    protected static ?string $navigationGroup = 'Kampagnen';

    protected static ?string $modelLabel = 'E-Mail-Vorlage';

    protected static ?string $pluralModelLabel = 'E-Mail-Vorlagen';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Vorlage')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Vorlagenname')
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'initial' => 'Erst-Mail',
                                'reminder_1' => '1. Erinnerung',
                                'reminder_2' => '2. Erinnerung',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('subject')
                            ->label('Betreff')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('body')
                            ->label('Inhalt')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3',
                                'bulletList', 'orderedList',
                                'link',
                                'undo', 'redo',
                            ]),
                        Forms\Components\Placeholder::make('placeholders')
                            ->label('Verfügbare Platzhalter')
                            ->content('{{anrede}}, {{name}}, {{email}}, {{kassenzeichen}}, {{link}}, {{frist}}, {{deadline}}, {{kampagne}}')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'initial' => 'Erst-Mail',
                        'reminder_1' => '1. Erinnerung',
                        'reminder_2' => '2. Erinnerung',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'initial' => 'primary',
                        'reminder_1' => 'warning',
                        'reminder_2' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Betreff')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aktualisiert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
