<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ViewCampaignStatistics extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CampaignResource::class;

    protected static string $view = 'filament.resources.campaign-resource.pages.view-campaign-statistics';

    public Campaign $record;

    public function getTitle(): string|Htmlable
    {
        return "Statistik: {$this->record->name}";
    }

    public function getBreadcrumb(): string
    {
        return 'Statistik';
    }

    public function getStats(): array
    {
        $recipients = $this->record->recipients();

        $total = $recipients->count();
        $sent = (clone $recipients)->where('email_status', 'sent')->count();
        $pending = (clone $recipients)->whereIn('email_status', ['pending', 'sending'])->count();
        $failed = (clone $recipients)->where('email_status', 'failed')->count();
        $accepted = (clone $recipients)->where('status', 'accepted')->count();
        $declined = (clone $recipients)->where('status', 'declined')->count();
        $opened = (clone $recipients)->whereNotNull('email_opened_at')->count();
        $clicked = (clone $recipients)->whereNotNull('email_clicked_at')->count();

        return [
            ['label' => 'Gesamt', 'value' => $total, 'color' => 'gray', 'icon' => 'heroicon-o-users'],
            ['label' => 'Versendet', 'value' => $sent, 'color' => 'success', 'icon' => 'heroicon-o-paper-airplane'],
            ['label' => 'Ausstehend', 'value' => $pending, 'color' => 'warning', 'icon' => 'heroicon-o-clock'],
            ['label' => 'Fehlgeschlagen', 'value' => $failed, 'color' => 'danger', 'icon' => 'heroicon-o-exclamation-triangle'],
            ['label' => 'Zugestimmt', 'value' => $accepted, 'color' => 'success', 'icon' => 'heroicon-o-check-circle'],
            ['label' => 'Abgelehnt', 'value' => $declined, 'color' => 'danger', 'icon' => 'heroicon-o-x-circle'],
            ['label' => 'Geöffnet', 'value' => $opened, 'color' => 'info', 'icon' => 'heroicon-o-envelope-open'],
            ['label' => 'Geklickt', 'value' => $clicked, 'color' => 'info', 'icon' => 'heroicon-o-cursor-arrow-rays'],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CampaignRecipient::query()
                    ->where('campaign_id', $this->record->id)
                    ->with('student')
            )
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.customer_number')
                    ->label('Kassenzeichen')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_status')
                    ->label('E-Mail-Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ausstehend',
                        'sending' => 'Wird gesendet',
                        'sent' => 'Gesendet',
                        'failed' => 'Fehlgeschlagen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'sending' => 'warning',
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('initial_sent_at')
                    ->label('Gesendet am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('–'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Antwort')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ausstehend',
                        'accepted' => 'Zugestimmt',
                        'declined' => 'Abgelehnt',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_error')
                    ->label('Fehler')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('–')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('email_opened_at')
                    ->label('Geöffnet')
                    ->boolean()
                    ->getStateUsing(fn (CampaignRecipient $record): bool => $record->email_opened_at !== null)
                    ->tooltip(fn (CampaignRecipient $record): ?string => $record->email_opened_at?->format('d.m.Y H:i')),
                Tables\Columns\IconColumn::make('email_clicked_at')
                    ->label('Geklickt')
                    ->boolean()
                    ->getStateUsing(fn (CampaignRecipient $record): bool => $record->email_clicked_at !== null)
                    ->tooltip(fn (CampaignRecipient $record): ?string => $record->email_clicked_at?->format('d.m.Y H:i')),
            ])
            ->defaultSort('student.name')
            ->filters([
                Tables\Filters\SelectFilter::make('email_status')
                    ->label('E-Mail-Status')
                    ->options([
                        'pending' => 'Ausstehend',
                        'sending' => 'Wird gesendet',
                        'sent' => 'Gesendet',
                        'failed' => 'Fehlgeschlagen',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Antwort')
                    ->options([
                        'pending' => 'Ausstehend',
                        'accepted' => 'Zugestimmt',
                        'declined' => 'Abgelehnt',
                    ]),
                Tables\Filters\TernaryFilter::make('opened')
                    ->label('Geöffnet')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_opened_at'),
                        false: fn (Builder $query) => $query->whereNull('email_opened_at'),
                    ),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Zurück zur Kampagne')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CampaignResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
