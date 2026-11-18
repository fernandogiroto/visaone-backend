<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            // text | request (pedido do agente com campo de resposta para o cliente)
            $table->string('kind', 20)->default('text')->after('body');
            $table->json('meta')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            $table->dropColumn(['kind', 'meta']);
        });
    }
};
