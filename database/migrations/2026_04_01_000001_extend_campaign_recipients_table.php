<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('email_status', 20)->default('pending')->after('status');
            $table->text('email_error')->nullable()->after('email_status');
            $table->tinyInteger('send_attempts')->default(0)->after('email_error');
            $table->uuid('tracking_id')->nullable()->after('send_attempts');
            $table->timestamp('email_opened_at')->nullable()->after('tracking_id');
            $table->timestamp('email_clicked_at')->nullable()->after('email_opened_at');
            $table->boolean('email_1_sent')->default(false)->after('email_clicked_at');
            $table->boolean('email_2_sent')->default(false)->after('email_1_sent');
        });

        // tracking_id für alle bestehenden Einträge generieren
        DB::table('campaign_recipients')->orderBy('id')->each(function ($recipient) {
            DB::table('campaign_recipients')
                ->where('id', $recipient->id)
                ->update(['tracking_id' => Str::uuid()->toString()]);
        });

        // Bestehende Daten migrieren: initial_sent_at gesetzt → email_status = 'sent'
        DB::table('campaign_recipients')
            ->whereNotNull('initial_sent_at')
            ->update([
                'email_status' => 'sent',
                'email_1_sent' => true,
            ]);

        // Jetzt unique constraint hinzufügen
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->unique('tracking_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropUnique(['tracking_id']);
            $table->dropColumn([
                'email_status',
                'email_error',
                'send_attempts',
                'tracking_id',
                'email_opened_at',
                'email_clicked_at',
                'email_1_sent',
                'email_2_sent',
            ]);
        });
    }
};
