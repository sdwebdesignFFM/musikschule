<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enum → String, damit 'paused' hinzugefügt werden kann
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // Paused-Kampagnen zuerst auf 'active' setzen, bevor Enum wiederhergestellt wird
        DB::table('campaigns')
            ->where('status', 'paused')
            ->update(['status' => 'active']);

        Schema::table('campaigns', function (Blueprint $table) {
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft')->change();
        });
    }
};
