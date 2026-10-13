<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('module_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['module_id', 'user_id']);
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('topic')->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('module_user');
        Schema::dropIfExists('modules');
    }
};
