<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
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
                                    ->label('Rückmeldefrist bis')
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
                        // ----- Locked-Info: laufende/abgeschlossene Kampagnen -----
                        Forms\Components\Placeholder::make('recipients_locked_info')
                            ->label('')
                            ->content(fn ($record) =>
                                "Diese Kampagne hat {$record->validRecipients()->count()} Empfänger. Die Empfänger-Liste kann nur im Entwurf-Status bearbeitet werden."
                            )
                            ->visible(fn ($record): bool => $record && ! $record->isDraft()),

                        // ----- Create-Modus: Toggle "Alle wählen" -----
                        Forms\Components\Toggle::make('select_all_students')
                            ->label('Alle aktiven Schüler auswählen')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('studentIds', []))
                            ->visible(fn ($record): bool => ! $record),
                        Forms\Components\Placeholder::make('student_count_info')
                            ->label('')
                            ->content(fn () => Student::where('active', true)->count() . ' aktive Schüler werden als Empfänger hinzugefügt.')
                            ->visible(fn ($record, Forms\Get $get): bool => ! $record && (bool) $get('select_all_students')),

                        // ----- Edit-Draft: aktuelle Anzahl + additive Bearbeitung -----
                        Forms\Components\Placeholder::make('current_recipients_info')
                            ->label('Aktueller Stand')
                            ->content(function ($record) {
                                $count = $record->validRecipients()->count();
                                return $count === 0
                                    ? 'Noch keine Empfänger zugewiesen.'
                                    : "{$count} Empfänger zugewiesen.";
                            })
                            ->visible(fn ($record): bool => $record && $record->isDraft()),

                        // Inline-Buttons direkt in der Section, damit sie nicht
                        // im Page-Header übersehen werden.
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('addAllActiveStudents')
                                ->label('Alle aktiven Schüler hinzufügen')
                                ->icon('heroicon-o-user-plus')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Alle aktiven Schüler hinzufügen')
                                ->modalDescription(function ($record) {
                                    $active = Student::where('active', true)->count();
                                    $existing = $record->recipients()->pluck('student_id');
                                    $toAdd = Student::where('active', true)
                                        ->whereNotIn('id', $existing)
                                        ->count();
                                    return "Aktuell aktiv: {$active} Schüler. Davon werden {$toAdd} neu hinzugefügt (bereits zugewiesene werden nicht doppelt angelegt).";
                                })
                                ->modalSubmitActionLabel('Ja, hinzufügen')
                                ->action(function ($record, $livewire) {
                                    $existing = $record->recipients()->pluck('student_id');
                                    $toAdd = Student::where('active', true)
                                        ->whereNotIn('id', $existing)
                                        ->pluck('id');

                                    // Eine Transaktion für 2000+ Inserts spart
                                    // den Commit-Overhead pro Row. CampaignRecipient::create
                                    // bleibt nötig, damit der creating()-Hook
                                    // token + tracking_id generiert.
                                    DB::transaction(function () use ($toAdd, $record) {
                                        foreach ($toAdd as $studentId) {
                                            CampaignRecipient::create([
                                                'campaign_id' => $record->id,
                                                'student_id' => $studentId,
                                            ]);
                                        }
                                    });

                                    \Filament\Notifications\Notification::make()
                                        ->success()
                                        ->title('Schüler hinzugefügt')
                                        ->body("{$toAdd->count()} neue Empfänger wurden hinzugefügt.")
                                        ->send();

                                    $livewire->refreshFormData(['current_recipients_info']);
                                }),
                            Forms\Components\Actions\Action::make('removeAllRecipients')
                                ->label('Alle Empfänger entfernen')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Alle Empfänger entfernen')
                                ->modalDescription(function ($record) {
                                    $count = $record->validRecipients()->count();
                                    return "Es werden {$count} Empfänger aus dieser Kampagne entfernt. Diese Aktion kann nicht rückgängig gemacht werden.";
                                })
                                ->modalSubmitActionLabel('Ja, alle entfernen')
                                ->visible(fn ($record): bool => $record && $record->recipients()->exists())
                                ->action(function ($record, $livewire) {
                                    $count = $record->validRecipients()->count();
                                    $record->recipients()->delete();

                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Empfänger entfernt')
                                        ->body("{$count} Empfänger wurden aus der Kampagne entfernt.")
                                        ->send();

                                    $livewire->refreshFormData(['current_recipients_info']);
                                }),
                        ])
                            ->visible(fn ($record): bool => $record && $record->isDraft()),

                        Forms\Components\Placeholder::make('add_recipients_hint')
                            ->label('')
                            ->content('Für einzelne Ergänzungen nutzen Sie die Suche unten.')
                            ->visible(fn ($record): bool => $record && $record->isDraft()),

                        // ----- Multi-Select: im Create ODER Edit-Draft, immer leer/additiv -----
                        Forms\Components\Select::make('studentIds')
                            ->label(fn ($record) => $record ? 'Schüler hinzufügen' : 'Schüler auswählen')
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
                            ->visible(function ($record, Forms\Get $get) {
                                // Create: nur wenn Toggle aus
                                if (! $record) {
                                    return ! $get('select_all_students');
                                }
                                // Edit: nur im Draft
                                return $record->isDraft();
                            })
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
                        'paused' => 'Pausiert',
                        'completed' => 'Abgeschlossen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'paused' => 'warning',
                        'completed' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('valid_recipients_count')
                    ->label('Empfänger')
                    ->counts('validRecipients')
                    ->sortable(),
                Tables\Columns\TextColumn::make('response_rate')
                    ->label('Rücklauf')
                    ->getStateUsing(function (Campaign $record): string {
                        $total = $record->validRecipients()->count();
                        if ($total === 0) return '–';
                        $responded = $record->validRecipients()->where('status', '!=', 'pending')->count();
                        return round(($responded / $total) * 100) . '%';
                    }),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Rückmeldefrist bis')
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
                        'paused' => 'Pausiert',
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
                        $dispatched = 0;

                        if ($initialEmail) {
                            $recipients = $record->validRecipients()->where('status', 'pending')->get();
                            foreach ($recipients as $index => $recipient) {
                                SendCampaignEmail::dispatch($recipient, $initialEmail)
                                    ->delay(now()->addSeconds($index * 2));
                            }
                            $dispatched = $recipients->count();
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Kampagne gestartet')
                            ->body("Die Kampagne wurde aktiviert. {$dispatched} E-Mail(s) werden versendet.")
                            ->send();
                    }),
                Tables\Actions\Action::make('pause')
                    ->label('Pausieren')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Kampagne pausieren')
                    ->modalDescription('Der Versand wird gestoppt. Jobs in der Queue werden beim nächsten Versuch übersprungen.')
                    ->modalSubmitActionLabel('Ja, pausieren')
                    ->visible(fn (Campaign $record): bool => $record->isActive())
                    ->action(function (Campaign $record): void {
                        $record->update(['status' => 'paused']);

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Kampagne pausiert')
                            ->body('Der Versand wurde gestoppt.')
                            ->send();
                    }),
                Tables\Actions\Action::make('resume')
                    ->label('Fortsetzen')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Kampagne fortsetzen')
                    ->modalDescription(function (Campaign $record): string {
                        $toSend = self::countResendable($record);
                        $excluded = self::countAlreadySent($record);

                        return "Es werden {$toSend} Empfänger versendet, die noch keine E-Mail erhalten haben."
                            . ($excluded > 0
                                ? " {$excluded} bereits versendete Empfänger werden ausgeschlossen (kein Doppel-Versand)."
                                : '');
                    })
                    ->modalSubmitActionLabel('Ja, fortsetzen')
                    ->visible(fn (Campaign $record): bool => $record->isPaused())
                    ->action(function (Campaign $record): void {
                        $record->update(['status' => 'active']);
                        $count = self::dispatchResendableRecipients($record);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Kampagne fortgesetzt')
                            ->body("{$count} E-Mail(s) werden versendet.")
                            ->send();
                    }),
                Tables\Actions\Action::make('retryFailed')
                    ->label('Fehler erneut senden')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Fehlgeschlagene E-Mails erneut senden')
                    ->modalDescription(function (Campaign $record): string {
                        $toSend = self::countResendable($record);
                        $excluded = self::countAlreadySent($record);

                        return "Es werden {$toSend} Empfänger versendet, die noch keine E-Mail erhalten haben."
                            . ($excluded > 0
                                ? " {$excluded} bereits versendete Empfänger werden ausgeschlossen (kein Doppel-Versand)."
                                : '');
                    })
                    ->modalSubmitActionLabel('Ja, erneut senden')
                    ->visible(fn (Campaign $record): bool => ($record->isActive() || $record->isPaused()) && self::countResendable($record) > 0)
                    ->action(function (Campaign $record): void {
                        if ($record->isPaused()) {
                            $record->update(['status' => 'active']);
                        }

                        $count = self::dispatchResendableRecipients($record);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Erneuter Versand gestartet')
                            ->body("{$count} E-Mail(s) werden erneut versendet.")
                            ->send();
                    }),
                Tables\Actions\Action::make('statistics')
                    ->label('Statistik')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->url(fn (Campaign $record) => static::getUrl('statistics', ['record' => $record]))
                    ->visible(fn (Campaign $record): bool => ! $record->isDraft()),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplizieren')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Kampagne duplizieren')
                    ->modalDescription('Es wird eine Kopie dieser Kampagne als Entwurf erstellt – inkl. E-Mails, Dokumente und Empfänger.')
                    ->modalSubmitActionLabel('Ja, duplizieren')
                    ->action(function (Campaign $record): void {
                        $newCampaign = $record->replicate(['status', 'recipients_count', 'valid_recipients_count']);
                        $newCampaign->name = "Kopie von {$record->name}";
                        $newCampaign->status = 'draft';
                        $newCampaign->save();

                        foreach ($record->emails as $email) {
                            $newCampaign->emails()->create($email->only(['type', 'subject', 'body', 'delay_days']));
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

                        // Nur gültige Empfänger (Student existiert noch) kopieren —
                        // verwaiste Empfänger bleiben in der Quell-Kampagne liegen
                        // und werden nicht in die Kopie gezogen.
                        foreach ($record->validRecipients as $recipient) {
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

    /**
     * Recipients, die noch KEINE Initial-Mail erhalten haben und neu versendet
     * werden können (Resume / Retry Failed).
     *
     * Basiert auf validRecipients() — verwaiste Empfänger (Student soft-deleted)
     * werden niemals dispatched, damit nicht an tote Adressen versendet wird.
     *
     * Doppel-Versand-Schutz auf mehreren Ebenen:
     *   1. whereNull('initial_sent_at') — Kern-Invariante: Wer jemals eine
     *      Initial-Mail erhalten hat, wird NIE wieder angeschrieben. Diese
     *      Prüfung ist resistent gegen den Reminder-Cron, der die
     *      email_1_sent / email_2_sent Flags temporär zurücksetzt.
     *   2. email_1_sent = false / email_2_sent = false — zusätzliche
     *      Sicherheit für den MailService, der auch pro Adresse skippt.
     *   3. status = 'pending' — schließt bereits reagierte Empfänger aus.
     */
    public static function resendableQuery(Campaign $record)
    {
        return $record->validRecipients()
            ->where('status', 'pending')
            ->whereNull('initial_sent_at')
            ->where('email_1_sent', false)
            ->where('email_2_sent', false)
            ->whereIn('email_status', ['pending', 'failed', 'sending']);
    }

    public static function countResendable(Campaign $record): int
    {
        return self::resendableQuery($record)->count();
    }

    public static function countAlreadySent(Campaign $record): int
    {
        // Konsistent mit resendableQuery: nur gültige Empfänger, bei denen die
        // Initial-Mail bereits vollständig versendet wurde.
        return $record->validRecipients()
            ->whereNotNull('initial_sent_at')
            ->count();
    }

    /**
     * Dispatched alle re-sendbaren Recipients als SendCampaignEmail-Job.
     * email_1_sent / email_2_sent werden NICHT zurückgesetzt — der MailService
     * prüft diese Flags pro Adresse und überspringt bereits versendete Mails.
     */
    public static function dispatchResendableRecipients(Campaign $record): int
    {
        $initialEmail = $record->emails()->where('type', 'initial')->first();
        if (! $initialEmail) {
            return 0;
        }

        // Hängende 'sending'-Locks ohne Versand zurücksetzen, damit
        // acquireSendLock() sie wieder fassen kann. whereNull('initial_sent_at')
        // schützt vor dem Reset eines hängenden Reminder-Dispatches. Nur gültige
        // Empfänger (validRecipients) — verwaiste Empfänger bleiben liegen.
        $record->validRecipients()
            ->where('email_status', 'sending')
            ->whereNull('initial_sent_at')
            ->where('email_1_sent', false)
            ->where('email_2_sent', false)
            ->update(['email_status' => 'pending']);

        $recipients = self::resendableQuery($record)->get();

        foreach ($recipients as $index => $recipient) {
            $recipient->update([
                'email_status' => 'pending',
                'email_error' => null,
            ]);

            SendCampaignEmail::dispatch($recipient, $initialEmail)
                ->delay(now()->addSeconds($index * 2));
        }

        return $recipients->count();
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
            'statistics' => Pages\ViewCampaignStatistics::route('/{record}/statistics'),
        ];
    }
}
