<?php

namespace App\Filament\Widgets;

use App\Models\CampaignRecipient;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestResponses extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Letzte Reaktionen';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CampaignRecipient::query()
                    ->whereNotNull('responded_at')
                    ->with(['student', 'campaign'])
                    ->latest('responded_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Schüler')
                    ->searchable(),
                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Kampagne')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->label('Antwort')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'accepted' => 'Zugesagt',
                        'declined' => 'Abgesagt',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('responded_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5)
            ->defaultSort('responded_at', 'desc');
    }
}
