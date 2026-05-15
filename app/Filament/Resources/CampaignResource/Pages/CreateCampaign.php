<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use App\Models\Student;
use App\Services\CampaignRecipientResolver;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';

        // E-Mail- und Empfänger-Felder aus dem Formular entfernen (separat behandelt).
        unset(
            $data['email_initial_subject'], $data['email_initial_body'],
            $data['email_reminder_1_subject'], $data['email_reminder_1_body'], $data['email_reminder_1_delay_days'],
            $data['email_reminder_2_subject'], $data['email_reminder_2_body'], $data['email_reminder_2_delay_days'],
            $data['studentIds'],
            $data['studentListIds'],
            $data['extraStudentIds'],
            $data['recipient_mode'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getState();
        $campaign = $this->record;

        // Kampagnen-E-Mails erstellen
        $this->saveEmails($campaign, $data);

        // Empfänger verknüpfen
        $this->saveRecipients($campaign, $data);
    }

    private function saveEmails($campaign, array $data): void
    {
        foreach (['initial', 'reminder_1', 'reminder_2'] as $type) {
            $subject = $data["email_{$type}_subject"] ?? null;
            $body = $data["email_{$type}_body"] ?? null;

            if ($subject && $body) {
                CampaignEmail::create([
                    'campaign_id' => $campaign->id,
                    'type' => $type,
                    'subject' => $subject,
                    'body' => $body,
                    'delay_days' => $type === 'initial' ? 0 : ($data["email_{$type}_delay_days"] ?? 7),
                ]);
            }
        }
    }

    private function saveRecipients($campaign, array $data): void
    {
        $resolver = app(CampaignRecipientResolver::class);
        $resolved = $resolver->resolve($data['recipient_mode'] ?? 'manuell', $data);

        if (! empty($resolved['listIds'])) {
            // Audit-Pivot append-only: niemals alte Audit-Einträge entfernen.
            $campaign->sourceLists()->syncWithoutDetaching($resolved['listIds']);
        }

        $existing = $campaign->recipients()->pluck('student_id');
        $toAdd = $resolved['ids']->diff($existing);

        foreach ($toAdd as $studentId) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'student_id' => $studentId,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
