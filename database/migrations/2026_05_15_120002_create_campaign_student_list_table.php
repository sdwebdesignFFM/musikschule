<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_student_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_list_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id', 'student_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_student_list');
    }
};
