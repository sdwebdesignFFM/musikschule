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

        // Empfänger laden
        $recipientCount = $campaign->recipients()->count();
        $activeStudentCount = Student::where('active', true)->count();

        if ($recipientCount > 0 && $recipientCount === $activeStudentCount) {
            $data['select_all_students'] = true;
            $data['studentIds'] = [];
        } else {
            $data['select_all_students'] = false;
            $data['studentIds'] = $campaign->recipients()->pluck('student_id')->toArray();
        }

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
        if (!empty($data['select_all_students'])) {
            $newStudentIds = Student::where('active', true)->pluck('id');
        } else {
            $newStudentIds = collect($data['studentIds'] ?? []);
        }

        $existingRecipients = $campaign->recipients()->get();
        $existingStudentIds = $existingRecipients->pluck('student_id');

        // Neue Empfänger hinzufügen
        $toAdd = $newStudentIds->diff($existingStudentIds);
        foreach ($toAdd as $studentId) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'student_id' => $studentId,
            ]);
        }

        // Entfernte Empfänger löschen (nur wenn noch nicht versendet)
        if (empty($data['select_all_students'])) {
            $toRemove = $existingStudentIds->diff($newStudentIds);
            $campaign->recipients()
                ->whereIn('student_id', $toRemove)
                ->whereNull('initial_sent_at')
                ->delete();
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
                        $recipients = $this->record->recipients()->where('status', 'pending')->get();
                        foreach ($recipients as $index => $recipient) {
                            SendCampaignEmail::dispatch($recipient, $initialEmail)
                                ->delay(now()->addSeconds($index * 2));
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Kampagne gestartet')
                        ->body("Die Kampagne wurde aktiviert. {$this->record->recipients()->count()} E-Mail(s) werden versendet.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
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
