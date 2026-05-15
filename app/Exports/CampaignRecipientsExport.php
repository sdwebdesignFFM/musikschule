<?php

namespace App\Exports;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CampaignRecipientsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(protected Campaign $campaign)
    {
    }

    public function query()
    {
        return CampaignRecipient::query()
            ->where('campaign_id', $this->campaign->id)
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.id', 'campaign_recipients.student_id')
                    ->whereNull('students.deleted_at');
            })
            ->with('student')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Name',
            'Kassenzeichen',
            'E-Mail',
            'E-Mail 2',
            'E-Mail-Status',
            'Gesendet am',
            'Antwort',
            'Geantwortet am',
            'Bestätigt über',
            'IP-Adresse',
            'Geöffnet am',
            'Geklickt am',
            'Fehler',
        ];
    }

    public function map($recipient): array
    {
        $emailStatus = match ($recipient->email_status) {
            'pending' => 'Ausstehend',
            'sending' => 'Wird gesendet',
            'sent' => 'Gesendet',
            'failed' => 'Fehlgeschlagen',
            default => (string) $recipient->email_status,
        };

        $responseStatus = match ($recipient->status) {
            'accepted' => 'Zugestimmt',
            'declined' => 'Abgelehnt',
            default => 'Ausstehend',
        };

        return [
            $recipient->student?->name ?? '',
            $recipient->student?->customer_number ?? '',
            $recipient->student?->email ?? '',
            $recipient->student?->email_2 ?? '',
            $emailStatus,
            $recipient->initial_sent_at?->format('d.m.Y H:i') ?? '',
            $responseStatus,
            $recipient->responded_at?->format('d.m.Y H:i') ?? '',
            $recipient->responded_via_email ?? '',
            $recipient->ip_address ?? '',
            $recipient->email_opened_at?->format('d.m.Y H:i') ?? '',
            $recipient->email_clicked_at?->format('d.m.Y H:i') ?? '',
            $recipient->email_error ?? '',
        ];
    }
}
