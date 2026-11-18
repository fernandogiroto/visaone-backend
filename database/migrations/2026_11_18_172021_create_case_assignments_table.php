<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            // offered | accepted | declined | closed (outro agente aceitou)
            $table->string('status', 20)->default('offered')->index();
            $table->timestamp('offered_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['immigration_case_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_assignments');
    }
};
