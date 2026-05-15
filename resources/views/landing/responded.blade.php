@extends('layouts.landing')

@section('title', 'Rückmeldung abgegeben – Musikschule Frankfurt')

@section('content')
<div class="max-w-[520px] mx-auto px-4 py-20">

    @if($accidentalResend ?? false)
        <div class="mb-8 bg-amber-50 border border-amber-200 rounded-xl p-5 text-left">
            <h2 class="text-amber-900 font-semibold text-sm mb-1">Hinweis zur heutigen E-Mail</h2>
            <p class="text-amber-800 text-sm leading-relaxed">
                Die heutige E-Mail wurde versehentlich verschickt. Ihre Rückmeldung
                haben wir bereits — die Angaben unten sind weiterhin gültig.
                Wir bitten die Störung zu entschuldigen.
            </p>
        </div>
    @endif

    <div class="text-center space-y-6">

        {{-- Success Circle --}}
        <div class="flex justify-center">
            <div class="w-20 h-20 rounded-full bg-green-50 flex items-center justify-center">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>

        {{-- Headline --}}
        <h1 class="text-navy text-xl font-bold">Wurde schon abgeschlossen</h1>

        {{-- Description --}}
        <p class="text-subtle text-[15px] leading-relaxed max-w-[520px]">
            Diese Rückmeldung wurde
            @if($recipient->responded_at)
                am <strong>{{ $recipient->responded_at->format('d.m.Y') }}</strong>
                um <strong>{{ $recipient->responded_at->format('H:i') }} Uhr</strong>
            @endif
            übermittelt. Eine erneute Übermittlung ist nicht möglich.
        </p>

        {{-- Summary Card --}}
        <div class="bg-[#f8f9fa] border border-border rounded-xl p-6 text-left space-y-4">
            <h2 class="text-navy font-semibold text-base">Ihre Entscheidung</h2>

            <div class="h-px bg-border"></div>

            <div class="flex justify-between items-center">
                <span class="text-muted text-sm">Status</span>
                @if($recipient->status === 'accepted')
                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">
                        Angenommen
                    </span>
                @else
                    <span class="bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full">
                        Abgelehnt
                    </span>
                @endif
            </div>

            <div class="flex justify-between items-center">
                <span class="text-muted text-sm">Eingereicht am</span>
                <span class="text-navy text-sm font-medium">
                    {{ $recipient->responded_at?->format('d.m.Y, H:i') }} Uhr
                </span>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-muted text-sm">Kassenzeichen</span>
                <span class="text-navy text-sm font-medium">
                    {{ $recipient->student->customer_number }}
                </span>
            </div>
        </div>

    </div>

</div>
@endsection
