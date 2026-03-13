<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Kampagnen';

    protected static ?string $navigationGroup = 'Kampagnen';

    protected static ?string $modelLabel = 'Kampagne';

    protected static ?string $pluralModelLabel = 'Kampagnen';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Sektion 1: Landingpage-Inhalte
                Forms\Components\Section::make('Inhalte zur Landingpage')
                    ->description('Diese Informationen werden auf der öffentlichen Landingpage angezeigt.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Kampagnenname')
                            ->required(),
                        Forms\Components\TextInput::make('subtitle')
                            ->label('Untertitel'),
                        Forms\Components\RichEditor::make('description')
                            ->label('Beschreibung')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'h2', 'h3',
                                'bulletList', 'orderedList',
                                'link',
                                'undo', 'redo',
                            ]),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Startdatum')
                                    ->required()
                                    ->displayFormat('d.m.Y'),
                                Forms\Components\DatePicker::make('deadline')
                                    ->label('Deadline')
                                    ->required()
                                    ->displayFormat('d.m.Y')
                                    ->afterOrEqual('start_date'),
                            ]),
                    ])->columns(2),

                // Sektion 2: Landingpage-Texte
                Forms\Components\Section::make('Texte auf der Landingpage')
                    ->description('Diese Texte werden auf der Rückmelde-Seite angezeigt. Leer lassen für Standardwerte.')
                    ->schema([
                        Forms\Components\Textarea::make('checkbox_text')
                            ->label('Checkbox-Text')
                            ->placeholder('Ich bestätige, dass ich die/der Zahlungspflichtige bin. Ich habe die Daten geprüft und die Informationen zur Kenntnis genommen.')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('accept_text')
                            ->label('Ja-Button Text')
                            ->placeholder('Ja, ich stimme zu'),
                        Forms\Components\TextInput::make('decline_text')
                            ->label('Nein-Button Text')
                            ->placeholder('Nein, ich stimme nicht zu. Mein Unterrichtsvertrag endet zum 31.7.2026'),
                    ])->columns(2)
                    ->collapsed(),

                // Sektion 3: Dokumente
                Forms\Components\Section::make('Unterlagen zur Kampagne')
                    ->description('Dokumente, die auf der Landingpage zum Download bereitgestellt werden.')
                    ->schema([
                        Forms\Components\TextInput::make('document_section_title')
                            ->label('Abschnittsüberschrift')
                            ->placeholder('z.B. Unterlagen zum Sommerkonzert'),
                        Forms\Components\Repeater::make('documents')
                            ->label('Dokumente')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('link_text')
                                    ->label('Linktext')
                                    ->required()
                                    ->placeholder('z.B. AGB als PDF'),
                                Forms\Components\FileUpload::make('file_path')
                                    ->label('Datei')
                                    ->required()
                                    ->directory('campaign-documents')
                                    ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
                            ])
                            ->columns(2)
                            ->orderColumn('sort_order')
                            ->addActionLabel('Dokument hinzufügen')
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                // Sektion 3: Erst-E-Mail
                self::emailSection('initial', 'Erst-E-Mail', 'Wird beim Start der Kampagne sofort versendet.'),

                // Sektion 4: 1. Erinnerung
                self::emailSection('reminder_1', '1. Erinnerung', 'Wird an Empfänger ohne Reaktion nach Ablauf der Tage versendet.'),

                // Sektion 5: 2. Erinnerung
                self::emailSection('reminder_2', '2. Erinnerung (letzte)', 'Letzte Erinnerung vor der Deadline.'),

                // Sektion 6: Empfänger
                Forms\Components\Section::make('Empfänger')
                    ->description('Wählen Sie die Schüler aus, die diese Kampagne erhalten sollen.')
                    ->schema([
                        Forms\Components\Toggle::make('select_all_students')
                            ->label('Alle aktiven Schüler auswählen')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('studentIds', [])),
                        Forms\Components\Placeholder::make('student_count_info')
                            ->label('')
                            ->content(fn () => Student::where('active', true)->count() . ' aktive Schüler werden als Empfänger hinzugefügt.')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('select_all_students')),
                        Forms\Components\Placeholder::make('current_recipients_info')
                            ->label('')
                            ->content(fn ($record) => $record ? 'Aktuell ' . $record->recipients()->count() . ' Empfänger zugewiesen.' : null)
                            ->visible(fn ($record, Forms\Get $get): bool => $record && !$get('select_all_students') && $record->recipients()->count() > 0),
                        Forms\Components\Select::make('studentIds')
                            ->label('Schüler auswählen')
                            ->multiple()
                            ->getSearchResultsUsing(fn (string $search) => Student::where('active', true)
                                ->where(fn ($q) => $q
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('customer_number', 'like', "%{$search}%"))
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->customer_number})"]))
                            ->getOptionLabelsUsing(fn (array $values) => Student::whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->customer_number})"]))
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => !$get('select_all_students'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function emailSection(string $type, string $title, string $description): Forms\Components\Section
    {
        $typeLabel = match ($type) {
            'initial' => 'Erst-Mail',
            'reminder_1' => '1. Erinnerung',
            'reminder_2' => '2. Erinnerung',
        };

        return Forms\Components\Section::make($title)
            ->description($description)
            ->schema([
                Forms\Components\Select::make("email_{$type}_template_id")
                    ->label('Vorlage laden')
                    ->options(EmailTemplate::where('type', $type)->pluck('name', 'id'))
                    ->placeholder('Vorlage auswählen...')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) use ($type) {
                        if ($state) {
                            $template = EmailTemplate::find($state);
                            if ($template) {
                                $set("email_{$type}_subject", $template->subject);
                                $set("email_{$type}_body", $template->body);
                            }
                        }
                    })
                    ->dehydrated(false),
                ...($type !== 'initial' ? [
                    Forms\Components\TextInput::make("email_{$type}_delay_days")
                        ->label('Tage nach Erst-Mail')
                        ->numeric()
                        ->default($type === 'reminder_1' ? 7 : 14)
                        ->required()
                        ->minValue(1),
                ] : []),
                Forms\Components\TextInput::make("email_{$type}_subject")
                    ->label('Betreff')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\RichEditor::make("email_{$type}_body")
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
                Forms\Components\Placeholder::make("email_{$type}_placeholders")
                    ->label('Verfügbare Platzhalter')
                    ->content('{{anrede}}, {{name}}, {{email}}, {{kassenzeichen}}, {{link}}, {{frist}}, {{deadline}}, {{kampagne}}')
                    ->columnSpanFull(),
            ])->columns(2)
            ->collapsed();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kampagne')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Entwurf',
                        'active' => 'Aktiv',
                        'completed' => 'Abgeschlossen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'completed' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('recipients_count')
                    ->label('Empfänger')
                    ->counts('recipients')
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_rate')
                    ->label('Rücklauf')
                    ->getStateUsing(function (Campaign $record): string {
                        $total = $record->recipients()->count();
                        if ($total === 0) return '–';
                        $responded = $record->recipients()->where('status', '!=', 'pending')->count();
                        return round(($responded / $total) * 100) . '%';
                    }),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Entwurf',
                        'active' => 'Aktiv',
                        'completed' => 'Abgeschlossen',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Vorschau')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Campaign $record) => route('landing.preview', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('start')
                    ->label('Starten')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-play')
                    ->modalIconColor('success')
                    ->modalHeading('Kampagne starten')
                    ->modalDescription('Sind Sie sicher? Alle Erst-Mails werden sofort an die Empfänger versendet. Diese Aktion kann nicht rückgängig gemacht werden.')
                    ->modalSubmitActionLabel('Ja, Kampagne starten')
                    ->visible(fn (Campaign $record): bool => $record->isDraft())
                    ->action(function (Campaign $record): void {
                        $record->update(['status' => 'active']);

                        $initialEmail = $record->emails()->where('type', 'initial')->first();
                        if ($initialEmail) {
                            $recipients = $record->recipients()->where('status', 'pending')->get();
                            foreach ($recipients as $index => $recipient) {
                                SendCampaignEmail::dispatch($recipient, $initialEmail)
                                    ->delay(now()->addSeconds($index * 2));
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Kampagne gestartet')
                            ->body("Die Kampagne wurde aktiviert. {$record->recipients()->count()} E-Mail(s) werden versendet.")
                            ->send();
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplizieren')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Kampagne duplizieren')
                    ->modalDescription('Es wird eine Kopie dieser Kampagne als Entwurf erstellt – inkl. E-Mails, Dokumente und Empfänger.')
                    ->modalSubmitActionLabel('Ja, duplizieren')
                    ->action(function (Campaign $record): void {
                        $newCampaign = $record->replicate(['status', 'recipients_count']);
                        $newCampaign->name = "Kopie von {$record->name}";
                        $newCampaign->status = 'draft';
                        $newCampaign->save();

                        foreach ($record->emails as $email) {
                            $newCampaign->emails()->create($email->only(['type', 'subject', 'body', 'delay_days', 'template_id']));
                        }

                        foreach ($record->documents as $doc) {
                            $newPath = $doc->file_path;
                            if (Storage::exists($doc->file_path)) {
                                $newPath = 'campaign-documents/' . uniqid() . '_' . basename($doc->file_path);
                                Storage::copy($doc->file_path, $newPath);
                            }
                            $newCampaign->documents()->create([
                                'link_text' => $doc->link_text,
                                'file_path' => $newPath,
                                'sort_order' => $doc->sort_order,
                            ]);
                        }

                        foreach ($record->recipients as $recipient) {
                            CampaignRecipient::create([
                                'campaign_id' => $newCampaign->id,
                                'student_id' => $recipient->student_id,
                            ]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Kampagne dupliziert')
                            ->body('Kampagne "' . $newCampaign->name . '" wurde als Entwurf erstellt.')
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Campaign $record): bool => $record->isDraft()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
