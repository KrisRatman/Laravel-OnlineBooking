<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Расписание хранится в местном времени бизнеса (TIME без даты), а не в UTC:
 * «пн 10:00–19:00» должно остаться 10:00 и после перевода часов.
 * В UTC оно переводится для конкретной даты при расчёте слотов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            // ISO: 1 — понедельник, 7 — воскресенье.
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->index(['staff_id', 'weekday']);
        });

        Schema::create('schedule_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->index(['staff_id', 'weekday']);
        });

        // Отпуск, больничный, сокращённый или дополнительный рабочий день.
        // На эту дату исключение полностью заменяет недельный шаблон вместе с перерывами.
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('type');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('note')->nullable();
            $table->unique(['staff_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('schedule_breaks');
        Schema::dropIfExists('working_hours');
    }
};
