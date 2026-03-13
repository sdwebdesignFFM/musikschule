<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('document_section_title')->nullable();
            $table->text('checkbox_text')->nullable();
            $table->text('accept_text')->nullable();
            $table->text('decline_text')->nullable();
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->date('start_date');
            $table->date('deadline');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
