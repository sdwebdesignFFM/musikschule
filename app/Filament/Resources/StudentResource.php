<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Empfänger';

    protected static ?string $navigationGroup = 'Kampagnen';

    protected static ?string $modelLabel = 'Empfänger';

    protected static ?string $pluralModelLabel = 'Empfänger';

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

                Forms\Components\Section::make('Kampagnen-Rückmeldungen')
                    ->schema([
                        Forms\Components\Placeholder::make('responses_table')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return 'Noch keine Rückmeldungen.';

                                $recipients = $record->campaignRecipients()
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
                Tables\Columns\TextColumn::make('latestRecipient.status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Student $record): ?string {
                        $latest = $record->campaignRecipients()->latest('updated_at')->first();
                        return $latest?->status;
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'accepted' => 'Angenommen',
                        'declined' => 'Gekündigt',
                        'pending' => 'Ausstehend',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted' => 'success',
                        'declined' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latestRecipient.responded_at')
                    ->label('Reaktion am')
                    ->getStateUsing(function (Student $record): ?string {
                        $latest = $record->campaignRecipients()->latest('updated_at')->first();
                        return $latest?->responded_at?->format('d.m.Y');
                    })
                    ->placeholder('—'),
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
                        if (filled($data['value'])) {
                            $query->whereHas('campaignRecipients', fn ($q) => $q->where('status', $data['value'])
                                ->whereIn('id', function ($sub) {
                                    $sub->selectRaw('MAX(id)')->from('campaign_recipients')->groupBy('student_id');
                                }));
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
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
                        Forms\Components\FileUpload::make('file')
                            ->label('Excel-Datei')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $import = new \App\Imports\StudentsImport();
                        $import->import(storage_path('app/public/' . $data['file']));

                        $failures = $import->failures();
                        if ($failures->isNotEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Import abgeschlossen mit Fehlern')
                                ->body($failures->count() . ' Zeile(n) konnten nicht importiert werden.')
                                ->persistent()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Import erfolgreich')
                                ->body('Alle Schüler wurden importiert.')
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
