<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('step_key', 60);
            // pending | submitted | approved | rejected
            $table->string('status', 20)->default('pending');
            $table->json('data')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['immigration_case_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_steps');
    }
};
