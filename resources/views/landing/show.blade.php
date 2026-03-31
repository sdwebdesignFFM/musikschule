@extends('layouts.landing')

@section('title', $campaignName . ' – Musikschule Frankfurt')

@section('content')
@if(!empty($isPreview))
<div class="bg-yellow-100 border-b border-yellow-300 text-yellow-800 text-center text-sm py-2 font-medium">
    Vorschau-Modus – Diese Seite ist nur für Admins sichtbar.
</div>
@endif
<div class="max-w-[700px] mx-auto px-4 py-7">

    {{-- Hero Section --}}
    <div class="text-center space-y-3 mb-7">
        <h1 class="text-[26px] font-bold text-navy leading-tight">
            {{ $campaignName }}
        </h1>

        @if($campaignSubtitle)
            <p class="text-navy text-lg font-semibold">
                {{ $campaignSubtitle }}
            </p>
        @endif
    </div>

    <div class="h-px bg-border mb-7"></div>

    <div class="text-center mb-7">
        <p class="text-navy text-lg font-semibold mb-4">
            Guten Tag {{ $recipient->student->name }},
        </p>

        @if($recipient->campaign->description)
            <div class="prose-modal text-subtle text-sm leading-[1.8] max-w-[600px] mx-auto">
                {!! $campaignDescription !!}
            </div>
        @endif
    </div>

    {{-- Data Card --}}
    <div class="bg-card border border-border rounded-[10px] p-[26px_30px] space-y-6 mb-7">

        {{-- Gespeicherte Daten --}}
        <h2 class="text-navy font-bold text-[17px]">Ihre bei uns gespeicherten Daten</h2>

        <div class="space-y-[18px]">
            {{-- Name + Kassenzeichen nebeneinander --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-[18px]">
                <div class="space-y-[5px]">
                    <label class="text-muted text-xs font-medium">Name des Zahlungspflichtigen</label>
                    <div class="bg-field-disabled border border-border-input rounded-[10px] px-4 py-[13px] text-navy text-[15px]">
                        {{ $recipient->student->name }}
                    </div>
                </div>

                <div class="space-y-[5px]">
                    <label class="text-muted text-xs font-medium">Kassenzeichen</label>
                    <div class="bg-field-disabled border border-border-input rounded-[10px] px-4 py-[13px] text-muted text-[15px]">
                        {{ $recipient->student->customer_number }}
                    </div>
                </div>
            </div>

            {{-- E-Mail-Adresse (nur die empfangene) --}}
            <div class="space-y-[5px]">
                <label class="text-muted text-xs font-medium">E-Mail-Adresse</label>
                <div class="bg-field-disabled border border-border-input rounded-[10px] px-4 py-[13px] text-navy text-[15px]">
                    {{ $displayEmail ?? $recipient->student->email }}
                </div>
            </div>
        </div>

        {{-- Divider --}}
        <div class="h-px bg-border"></div>

        <form method="POST" action="{{ route('landing.respond', $recipient->token) }}" id="responseForm">
            @csrf

            {{-- Dokumente als Pflicht-Checkboxen --}}
            @if($recipient->campaign->documents->isNotEmpty())
                <div class="space-y-[12px]">
                    <h3 class="text-navy text-sm font-semibold">
                        {{ $recipient->campaign->document_section_title ?? 'Zugehörige Dokumente' }}
                    </h3>

                    @foreach($recipient->campaign->documents as $index => $doc)
                        <div class="flex items-start gap-[10px]">
                            <input type="checkbox"
                                   id="doc_{{ $index }}"
                                   name="doc_{{ $index }}"
                                   required
                                   class="mt-0.5 w-[18px] h-[18px] rounded border-[#B8C5D3] border-[1.5px] text-primary focus:ring-primary shrink-0">
                            <label for="doc_{{ $index }}" class="text-subtle text-[13px] leading-relaxed">
                                Ich habe die
                                <button type="button"
                                        onclick="openDocumentModal('{{ Storage::url($doc->file_path) }}', '{{ e($doc->link_text) }}')"
                                        class="text-primary font-medium underline hover:text-primary-dark">{{ $doc->link_text }}</button>
                                gelesen und zur Kenntnis genommen.
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- Divider --}}
                <div class="h-px bg-border my-6"></div>
            @endif

            {{-- Bestätigung Zahlungspflichtiger --}}
            <div class="flex items-start gap-[10px] mb-6">
                <input type="checkbox"
                       id="confirmation"
                       name="confirmation"
                       required
                       class="mt-0.5 w-[18px] h-[18px] rounded border-[#B8C5D3] border-[1.5px] text-primary focus:ring-primary shrink-0">
                <label for="confirmation" class="text-subtle text-[13px] leading-relaxed">
                    {{ $recipient->campaign->checkbox_text ?? 'Ich bestätige, dass ich die/der Zahlungspflichtige bin. Ich habe die Daten geprüft und die Informationen zur Kenntnis genommen.' }}
                </label>
            </div>

            <div class="space-y-3">
                <button type="submit"
                        name="response"
                        value="accepted"
                        class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-semibold text-base py-[15px] px-6 rounded-xl transition-colors">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ $recipient->campaign->accept_text ?? 'Ich stimme den Vertragsänderungen zu' }}
                </button>

                <button type="submit"
                        name="response"
                        value="declined"
                        class="w-full flex items-center justify-center bg-white hover:bg-gray-50 text-muted text-sm py-[13px] px-6 rounded-xl border border-[#CBD5E1] transition-colors">
                    {{ $recipient->campaign->decline_text ?? 'Nein, ich stimme nicht zu. Mein Unterrichtsvertrag endet zum 31.7.2026' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Trust Note --}}
    <div class="text-center">
        <p class="text-muted-light text-xs">
            Ihre Rückmeldung wird gespeichert und Sie erhalten eine Bestätigung per E-Mail.
        </p>
    </div>

</div>
@endsection
