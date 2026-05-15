@extends('layouts.landing')

@section('title', 'Kampagne beendet – Musikschule Frankfurt')

@section('content')
<div class="max-w-[520px] mx-auto px-4 py-20">

    <div class="text-center space-y-6">

        {{-- Expired Circle --}}
        <div class="flex justify-center">
            <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Headline --}}
        <h1 class="text-navy text-xl font-bold">Die Kampagne ist beendet</h1>

        {{-- Description --}}
        <p class="text-subtle text-[15px] leading-relaxed max-w-[520px]">
            Die Rückmeldefrist ist überschritten. Eine Rückmeldung über diese Seite
            ist nicht mehr möglich. Bei Rückfragen wenden Sie sich bitte direkt an
            die Musikschule.
        </p>

        {{-- Summary Card --}}
        <div class="bg-[#f8f9fa] border border-border rounded-xl p-6 text-left space-y-4">
            <h2 class="text-navy font-semibold text-base">Informationen</h2>

            <div class="h-px bg-border"></div>

            <div class="flex justify-between items-center">
                <span class="text-muted text-sm">Rückmeldefrist war</span>
                <span class="text-navy text-sm font-medium">
                    {{ $recipient->campaign->deadline?->format('d.m.Y') ?? '—' }}
                </span>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-muted text-sm">Kassenzeichen</span>
                <span class="text-navy text-sm font-medium">
                    {{ $recipient->student?->customer_number ?? '—' }}
                </span>
            </div>
        </div>

    </div>

</div>
@endsection
