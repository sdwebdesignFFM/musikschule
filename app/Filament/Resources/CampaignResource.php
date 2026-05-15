<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Student;
use App\Models\StudentList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
                    ->description(new HtmlString(
                        '<div class="flex items-start gap-2 text-sm text-warning-700">'
                        . '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 12.75v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>'
                        . '<span>Empfänger werden beim Speichern fixiert (Snapshot). Spätere Listen-Änderungen wirken sich nur auf neue Kampagnen aus.</span>'
                        . '</div>'
                    ))
                    ->schema([
                        // ----- Locked-Info: laufende/abgeschlossene Kampagnen -----
                        Forms\Components\Placeholder::make('recipients_locked_info')
                            ->label('')
                            ->content(fn ($record) =>
                                "Diese Kampagne hat {$record->valid_recipients_count} Empfänger. Die Empfänger-Liste kann nur im Entwurf-Status bearbeitet werden."
                            )
                            ->visible(fn ($record): bool => $record && ! $record->isDraft()),

                        // ----- Edit-Draft: aktuelle Anzahl + Hinweis-Banner -----
                        Forms\Components\Placeholder::make('current_recipients_info')
                            ->label('Aktueller Stand')
                            ->content(function ($record) {
                                $count = $record->valid_recipients_count ?? $record->validRecipients()->count();
                                return $count === 0
                                    ? 'Noch keine Empfänger zugewiesen.'
                                    : "{$count} Empfänger zugewiesen.";
                            })
                            ->visible(fn ($record): bool => $record && $record->isDraft()),

                        Forms\Components\Placeholder::make('edit_additive_banner')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-info-50 px-4 py-3 text-sm text-info-700">'
                                . 'Speichern fügt nur fehlende Schüler hinzu. Bereits vorhandene Empfänger bleiben unverändert. '
                                . 'Listen aus der Auswahl zu entfernen <strong>löscht keine bestehenden Empfänger</strong> &mdash; '
                                . 'dafür gibt es die Aktion „Alle Empfänger entfernen" weiter unten.'
                                . '</div>'
                            ))
                            ->visible(fn ($record): bool => $record && $record->isDraft() && $record->recipients()->exists()),

                        // ----- Mode-Switch: ToggleButtons (Create + Edit-Draft) -----
                        Forms\Components\ToggleButtons::make('recipient_mode')
                            ->label('Empfänger-Quelle')
                            ->options([
                                'aus_listen' => 'Aus Liste(n)',
                                'manuell'    => 'Manuelle Auswahl',
                            ])
                            ->icons([
                                'aus_listen' => 'heroicon-o-user-group',
                                'manuell'    => 'heroicon-o-cursor-arrow-rays',
                            ])
                            ->colors([
                                'aus_listen' => 'success',
                                'manuell'    => 'warning',
                            ])
                            ->default('aus_listen')
                            ->inline()
                            ->live()
                            ->visible(fn ($record): bool => ! $record || $record->isDraft()),

                        // Modus „Aus Listen"
                        Forms\Components\Select::make('studentListIds')
                            ->label('Listen')
                            ->multiple()
                            ->options(fn () => StudentList::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Aktuelle Mitglieder werden als Empfänger eingefroren.')
                            ->visible(fn (Forms\Get $get, $record): bool =>
                                (! $record || $record->isDraft()) && $get('recipient_mode') === 'aus_listen'
                            )
                            ->columnSpanFull(),

                        Forms\Components\Select::make('extraStudentIds')
                            ->label('Plus weitere Einzelschüler (optional)')
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
                            ->visible(fn (Forms\Get $get, $record): bool =>
                                (! $record || $record->isDraft()) && $get('recipient_mode') === 'aus_listen'
                            )
                            ->columnSpanFull(),

                        // Modus „Manuell"
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
                            ->visible(fn (Forms\Get $get, $record): bool =>
                                (! $record || $record->isDraft()) && $get('recipient_mode') === 'manuell'
                            )
                            ->columnSpanFull(),

                        // „Alle Empfänger entfernen" bleibt als Cleanup-Action im Edit-Draft erhalten.
                        Forms\Components\Actions::make([
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
                                ->action(function ($record, $livewire) {
                                    $count = $record->validRecipients()->count();
                                    $record->recipients()->delete();

                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Empfänger entfernt')
                                        ->body("{$count} Empfänger wurden aus der Kampagne entfernt.")
                                        ->send();

                                    $livewire->refreshFormData(['current_recipients_info', 'edit_additive_banner']);
                                }),
                        ])
                            ->visible(fn ($record): bool => $record && $record->isDraft() && $record->recipients()->exists()),
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
                    ->modalDescription(fn (Campaign $record): HtmlString => self::buildStartModalDescription($record))
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

    /**
     * Modal-Beschreibung der "Starten"-Action: Anzahl-Zeile (aus ef7f92b) bleibt,
     * Mehrfach-Teilnahme-Warnung erscheint nur wenn relevant.
     */
    public static function buildStartModalDescription(Campaign $record): HtmlString
    {
        $count = $record->validRecipients()->where('status', 'pending')->count();
        $intro = "Diese Kampagne wird an <strong>{$count}</strong> Empfänger versendet.";

        $recent = self::countRecentlyContactedRecipients($record);
        $warning = $recent > 0
            ? '<p class="mt-2 text-warning-600"><strong>⚠️ ' . $recent . '</strong> davon haben in den letzten 7 Tagen bereits Mails aus anderen Kampagnen erhalten.</p>'
            : '';

        return new HtmlString("<p>{$intro}</p>{$warning}");
    }

    /**
     * Flacher Subquery-Join für die Pre-Send-Warnung. Schneller als doppeltes
     * whereHas. Zählt distinct Empfänger, die in den letzten 7 Tagen aus einer
     * anderen Kampagne eine Initial-Mail bekommen haben.
     */
    public static function countRecentlyContactedRecipients(Campaign $record): int
    {
        return DB::table('campaign_recipients as cr1')
            ->join('campaign_recipients as cr2', 'cr2.student_id', '=', 'cr1.student_id')
            ->where('cr1.campaign_id', $record->id)
            ->where('cr1.status', 'pending')
            ->where('cr2.campaign_id', '!=', $record->id)
            ->whereNotNull('cr2.initial_sent_at')
            ->where('cr2.initial_sent_at', '>=', now()->subDays(7))
            ->distinct()
            ->count('cr1.student_id');
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
