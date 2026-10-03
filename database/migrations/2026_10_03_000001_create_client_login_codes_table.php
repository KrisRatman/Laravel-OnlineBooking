<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Клиент входит в личный кабинет по одноразовому коду, пароля нет.
            $table->rememberToken()->after('notes');
        });

        Schema::create('client_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            // Храним хеш: утечка таблицы не даёт войти по свежему коду.
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_login_codes');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
