<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Jobs\SendCampaignEmail;
use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use App\Models\Student;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $campaign = $this->record;

        // E-Mail-Daten in das Formular laden
        foreach ($campaign->emails as $email) {
            $type = $email->type;
            $data["email_{$type}_subject"] = $email->subject;
            $data["email_{$type}_body"] = $email->body;

            if ($type !== 'initial') {
                $data["email_{$type}_delay_days"] = $email->delay_days;
            }
        }

        // Empfänger werden im Edit-Modus NICHT als Tags ins Multi-Select
        // geladen (sonst 2000+ DOM-Nodes). Stattdessen wird nur die Anzahl
        // angezeigt und das Multi-Select arbeitet additiv.
        $data['select_all_students'] = false;
        $data['studentIds'] = [];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // E-Mail-Felder und Empfänger aus den Kampagnendaten entfernen
        unset(
            $data['email_initial_subject'], $data['email_initial_body'],
            $data['email_reminder_1_subject'], $data['email_reminder_1_body'], $data['email_reminder_1_delay_days'],
            $data['email_reminder_2_subject'], $data['email_reminder_2_body'], $data['email_reminder_2_delay_days'],
            $data['studentIds'],
            $data['select_all_students'],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();
        $campaign = $this->record;

        // E-Mails aktualisieren
        $this->saveEmails($campaign, $data);

        // Empfänger synchronisieren
        $this->syncRecipients($campaign, $data);
    }

    private function saveEmails($campaign, array $data): void
    {
        foreach (['initial', 'reminder_1', 'reminder_2'] as $type) {
            $subject = $data["email_{$type}_subject"] ?? null;
            $body = $data["email_{$type}_body"] ?? null;

            if ($subject && $body) {
                CampaignEmail::updateOrCreate(
                    ['campaign_id' => $campaign->id, 'type' => $type],
                    [
                        'subject' => $subject,
                        'body' => $body,
                        'delay_days' => $type === 'initial' ? 0 : ($data["email_{$type}_delay_days"] ?? 7),
                    ]
                );
            }
        }
    }

    private function syncRecipients($campaign, array $data): void
    {
        // Im Edit-Modus arbeitet das Formular additiv:
        // - studentIds enthält nur NEU hinzuzufügende Schüler
        // - Bestehende Empfänger werden nie über das Form gelöscht
        // - Bulk-Operationen (alle hinzufügen / alle entfernen) laufen über
        //   Header-Actions, nicht über Save.
        if (! $campaign->isDraft()) {
            return;
        }

        $newStudentIds = collect($data['studentIds'] ?? []);
        if ($newStudentIds->isEmpty()) {
            return;
        }

        $existingStudentIds = $campaign->recipients()->pluck('student_id');
        $toAdd = $newStudentIds->diff($existingStudentIds);

        foreach ($toAdd as $studentId) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'student_id' => $studentId,
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Vorschau')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('landing.preview', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('statistics')
                ->label('Statistik')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(fn () => $this->getResource()::getUrl('statistics', ['record' => $this->record]))
                ->visible(fn (): bool => ! $this->record->isDraft()),
            Actions\Action::make('start')
                ->label('Kampagne starten')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Kampagne starten')
                ->modalDescription('Sind Sie sicher? Alle Erst-Mails werden sofort an die Empfänger versendet.')
                ->modalSubmitActionLabel('Ja, Kampagne starten')
                ->visible(fn (): bool => $this->record->isDraft())
                ->action(function (): void {
                    $this->record->update(['status' => 'active']);

                    $initialEmail = $this->record->emails()->where('type', 'initial')->first();
                    if ($initialEmail) {
                        // validRecipients: verwaiste Empfänger (Student soft-deleted)
                        // werden niemals dispatched.
                        $recipients = $this->record->validRecipients()->where('status', 'pending')->get();
                        foreach ($recipients as $index => $recipient) {
                            SendCampaignEmail::dispatch($recipient, $initialEmail)
                                ->delay(now()->addSeconds($index * 2));
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Kampagne gestartet')
                        ->body("Die Kampagne wurde aktiviert. {$this->record->validRecipients()->count()} E-Mail(s) werden versendet.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('pause')
                ->label('Pausieren')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Kampagne pausieren')
                ->modalDescription('Der Versand wird gestoppt.')
                ->modalSubmitActionLabel('Ja, pausieren')
                ->visible(fn (): bool => $this->record->isActive())
                ->action(function (): void {
                    $this->record->update(['status' => 'paused']);

                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title('Kampagne pausiert')
                        ->send();
                }),
            Actions\Action::make('resume')
                ->label('Fortsetzen')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Kampagne fortsetzen')
                ->modalDescription(function () {
                    $count = CampaignResource::countResendable($this->record);
                    $alreadySent = CampaignResource::countAlreadySent($this->record);
                    return "Es werden {$count} Empfänger versendet, die bisher noch nichts erhalten haben. {$alreadySent} bereits versendete Empfänger werden ausgeschlossen (kein Doppel-Versand).";
                })
                ->modalSubmitActionLabel('Ja, fortsetzen')
                ->visible(fn (): bool => $this->record->isPaused())
                ->action(function (): void {
                    $this->record->update(['status' => 'active']);

                    // Einheitlicher Dispatch-Pfad — identisch zur Resume-Action
                    // in der Listen-Ansicht (CampaignResource). Kein Drift mehr.
                    $count = CampaignResource::dispatchResendableRecipients($this->record);

                    if ($count === 0) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Nichts zu versenden')
                            ->body('Entweder ist keine Erst-Mail definiert oder alle Empfänger haben bereits eine Mail erhalten.')
                            ->send();
                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Kampagne fortgesetzt')
                        ->body("{$count} E-Mail(s) werden versendet. Bereits versendete Empfänger wurden ausgeschlossen.")
                        ->send();
                }),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
