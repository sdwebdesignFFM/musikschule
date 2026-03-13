<?php

use Illuminate\Support\Facades\Schedule;

// Täglich um 8:00 Uhr: Erinnerungen senden
Schedule::command('campaigns:send-reminders')->dailyAt('08:00');

// Täglich um 0:05 Uhr: Abgelaufene Kampagnen abschließen
Schedule::command('campaigns:complete')->dailyAt('00:05');
