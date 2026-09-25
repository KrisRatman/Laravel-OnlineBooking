<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            // UTC.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Снимок условий на момент записи: смена цены услуги не меняет старые записи.
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->string('status')->default('new');
            $table->text('comment')->nullable();
            // Секрет из ссылки для клиента: посмотреть и отменить запись без регистрации.
            $table->string('token', 64)->unique();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('reminder_day_sent_at')->nullable();
            $table->dateTime('reminder_hours_sent_at')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
