<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Models\Student;
use App\Models\StudentList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Schüler';

    protected static ?string $navigationGroup = 'Kampagnen';

    protected static ?string $modelLabel = 'Schüler';

    protected static ?string $pluralModelLabel = 'Schüler';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Persönliche Daten')
                    ->schema([
                        Forms\Components\TextInput::make('customer_number')
                            ->label('Kassenzeichen')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('z.B. MS-1042'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('E-Mail')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('email_2')
                            ->label('Zweite E-Mail')
                            ->email(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel(),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Aktiv')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Listen-Mitgliedschaft')
                    ->description('Listen, in denen dieser Schüler enthalten ist. Hier können Listen zugewiesen oder entfernt werden.')
                    ->schema([
                        Forms\Components\Select::make('studentLists')
                            ->label('Listen')
                            ->multiple()
                            ->relationship('studentLists', 'name')
                            ->preload()
                            ->searchable()
                            ->placeholder('Keine Liste zugewiesen')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Kampagnen-Rückmeldungen')
                    ->schema([
                        Forms\Components\Placeholder::make('responses_table')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return 'Noch keine Rückmeldungen.';

                                $recipients = $record->campaignRecipients()
                                    ->whereHas('campaign')
                                    ->with('campaign')
                                    ->latest('updated_at')
                                    ->get();

                                if ($recipients->isEmpty()) return 'Noch keine Rückmeldungen.';

                                $rows = $recipients->map(function ($r) {
                                    $status = match ($r->status) {
                                        'accepted' => '<span style="color:#22C55E;font-weight:600">Angenommen</span>',
                                        'declined' => '<span style="color:#EF4444;font-weight:600">Gekündigt</span>',
                                        'pending' => '<span style="color:#F59E0B;font-weight:600">Ausstehend</span>',
                                        default => '—',
                                    };
                                    $date = $r->responded_at?->format('d.m.Y H:i') ?? '—';
                                    $ip = $r->ip_address ?? '—';
                                    $campaign = e($r->campaign->name ?? '—');

                                    return "<tr>
                                        <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>{$campaign}</td>
                                        <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>{$status}</td>
                                        <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>{$date}</td>
                                        <td style='padding:8px 12px;border-bottom:1px solid #e5e7eb'>{$ip}</td>
                                    </tr>";
                                })->join('');

                                return new \Illuminate\Support\HtmlString("
                                    <table style='width:100%;border-collapse:collapse;font-size:14px'>
                                        <thead>
                                            <tr style='text-align:left;border-bottom:2px solid #d1d5db'>
                                                <th style='padding:8px 12px'>Kampagne</th>
                                                <th style='padding:8px 12px'>Status</th>
                                                <th style='padding:8px 12px'>Reaktion am</th>
                                                <th style='padding:8px 12px'>IP-Adresse</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                ");
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'campaignRecipients' => fn ($q) => $q->whereHas('campaign'),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email_2')
                    ->label('E-Mail 2')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('customer_number')
                    ->label('Kassenzeichen')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('campaign_recipients_count')
                    ->label('In Kampagnen')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->sortable()
                    ->url(fn (Student $record): ?string => $record->campaign_recipients_count > 0
                        ? StudentResource::getUrl('edit', ['record' => $record])
                        : null
                    )
                    ->toggleable(),
                Tables\Columns\TextColumn::make('studentLists.name')
                    ->label('Listen')
                    ->badge()
                    ->separator(',')
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Aktiv/Inaktiv')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive')
                    ->placeholder('Alle'),
                Tables\Filters\SelectFilter::make('response_status')
                    ->label('Rückmeldung')
                    ->options([
                        'accepted' => 'Angenommen',
                        'declined' => 'Gekündigt',
                        'pending' => 'Ausstehend',
                    ])
                    ->query(function ($query, array $data) {
                        if (! filled($data['value'])) {
                            return;
                        }
                        if ($data['value'] === 'pending') {
                            // Ausstehend = es gibt keinerlei accepted/declined-Antwort
                            // in einer noch existierenden Kampagne.
                            $query->whereDoesntHave('campaignRecipients', fn ($q) =>
                                $q->whereIn('status', ['accepted', 'declined'])
                                    ->whereHas('campaign')
                            );
                        } else {
                            $query->whereHas('campaignRecipients', fn ($q) =>
                                $q->where('status', $data['value'])
                                    ->whereHas('campaign')
                            );
                        }
                    }),
                Tables\Filters\SelectFilter::make('in_lists')
                    ->label('In Liste(n)')
                    ->multiple()
                    ->options(fn () => StudentList::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) =>
                        empty($data['values']) ? $query :
                        $query->whereHas('studentLists', fn (Builder $q) =>
                            $q->whereIn('student_lists.id', $data['values'])
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('addToList')
                    ->label('Zur Liste hinzufügen')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Collection $records) => $records->count() . ' Schüler zu Liste hinzufügen?')
                    ->modalDescription('Bereits in der Liste vorhandene Schüler werden automatisch übersprungen.')
                    ->form(function () {
                        $listsExist = StudentList::exists();

                        return [
                            Forms\Components\Placeholder::make('no_lists_hint')
                                ->label('')
                                ->content(new \Illuminate\Support\HtmlString(
                                    'Noch keine Listen vorhanden. <a href="'
                                    . StudentListResource::getUrl('create')
                                    . '" target="_blank" class="text-primary-600 underline">Neue Liste anlegen →</a>'
                                ))
                                ->visible(! $listsExist),
                            Forms\Components\Select::make('list_id')
                                ->label('Liste')
                                ->options(StudentList::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->visible($listsExist),
                        ];
                    })
                    ->action(function (Collection $records, array $data): void {
                        if (empty($data['list_id'])) {
                            return;
                        }

                        $list = StudentList::findOrFail($data['list_id']);
                        $list->allMembers()->syncWithoutDetaching($records->pluck('id')->all());

                        Notification::make()
                            ->success()
                            ->title($records->count() . ' Schüler hinzugefügt')
                            ->body('Liste „' . $list->name . '"')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Excel-Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return (new \App\Exports\StudentsExport())->download('schueler-' . now()->format('Y-m-d') . '.xlsx');
                    }),
                Tables\Actions\Action::make('import')
                    ->label('Excel-Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\Placeholder::make('template_download')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<a href="/beispiel-import.xlsx" class="text-sm text-primary-600 hover:underline">Beispiel-Excel herunterladen</a>'
                                . '<br><span class="text-xs text-gray-500">Spalten: kassenzeichen, name, email, email_2</span>'
                            )),
                        Forms\Components\FileUpload::make('file')
                            ->label('Excel-Datei')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->required(),
                        Forms\Components\Select::make('list_id')
                            ->label('Importierte Schüler einer Liste hinzufügen (optional)')
                            ->options(fn () => StudentList::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Keine Liste — Schüler nur in Stammdaten anlegen')
                            ->helperText('Falls ausgewählt, werden alle erfolgreich importierten Schüler dieser Liste hinzugefügt.'),
                    ])
                    ->action(function (array $data): void {
                        $list = ! empty($data['list_id'])
                            ? StudentList::find($data['list_id'])
                            : null;
                        $import = new \App\Imports\StudentsImport($list);

                        try {
                            $import->import(storage_path('app/public/' . $data['file']));
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Import fehlgeschlagen')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                            return;
                        }

                        $failures = $import->failures();
                        if ($failures->isNotEmpty()) {
                            $details = $failures->take(10)->map(
                                fn ($f) => 'Zeile ' . $f->row() . ': ' . implode(', ', $f->errors())
                            )->implode("\n");
                            $more = $failures->count() > 10 ? "\n… und " . ($failures->count() - 10) . ' weitere.' : '';

                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title($failures->count() . ' Zeile(n) übersprungen')
                                ->body($details . $more)
                                ->persistent()
                                ->send();
                        } else {
                            $body = 'Alle Schüler wurden importiert.';
                            if ($list) {
                                $body .= ' Sie wurden zur Liste „' . $list->name . '" hinzugefügt.';
                            }
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Import erfolgreich')
                                ->body($body)
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
