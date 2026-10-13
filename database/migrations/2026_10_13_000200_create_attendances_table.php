<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10); // present | absent
            $table->dateTime('marked_at')->nullable(); // hora real de la marca del estudiante
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Una sola marca por estudiante y fecha de clase.
            $table->unique(['class_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
