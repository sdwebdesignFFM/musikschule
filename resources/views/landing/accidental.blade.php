@extends('layouts.landing')

@section('title', 'Versehentlich versendet – Musikschule Frankfurt')

@section('content')
<div class="max-w-[520px] mx-auto px-4 py-20">

    <div class="text-center space-y-6">

        {{-- Info Circle --}}
        <div class="flex justify-center">
            <div class="w-20 h-20 rounded-full bg-amber-50 flex items-center justify-center">
                <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
        </div>

        {{-- Headline --}}
        <h1 class="text-navy text-xl font-bold">Versehentlich versendet</h1>

        {{-- Description --}}
        <p class="text-subtle text-[15px] leading-relaxed">
            Die E-Mail, über die Sie hier hergekommen sind, wurde versehentlich verschickt.
            Bitte ignorieren Sie diese Nachricht.
        </p>

        <p class="text-subtle text-[15px] leading-relaxed">
            Sollte für Sie noch eine Rückmeldung offen sein, ist Ihre ursprüngliche
            E-Mail von uns weiterhin gültig — bitte verwenden Sie den Link aus dieser
            E-Mail. Bei Rückfragen wenden Sie sich gern direkt an die Musikschule.
        </p>

        <p class="text-subtle text-[14px] leading-relaxed">
            Wir bitten die Störung zu entschuldigen.
        </p>

        @if($recipient->student?->customer_number)
            <div class="bg-[#f8f9fa] border border-border rounded-xl p-6 text-left">
                <div class="flex justify-between items-center">
                    <span class="text-muted text-sm">Kassenzeichen</span>
                    <span class="text-navy text-sm font-medium">
                        {{ $recipient->student->customer_number }}
                    </span>
                </div>
            </div>
        @endif

    </div>

</div>
@endsection
