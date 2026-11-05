<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->cascandeOnDelete();
            $table->string('name');
            $table->string('pic_name')->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('progress', 5, 2)->default(0);
            $table->string('status', 20)->default('not_started')->index();
            $table->string('priority', 20)->default('normal');
            $table->date('planned_start')->nullable();
            $table->date('target_end')->nullable();
            $table->decimal('budget_plan', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};