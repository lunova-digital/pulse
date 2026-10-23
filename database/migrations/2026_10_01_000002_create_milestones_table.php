<?php

use App\Enums\MilestoneState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->unsignedInteger('position');
            $table->enum('state', array_column(MilestoneState::cases(), 'value'))->default(MilestoneState::Upcoming->value);

            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
