<?php

use App\Enums\WhoseMove;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_name')->nullable();
            $table->text('description')->nullable();
            $table->enum('whose_move', array_column(WhoseMove::cases(), 'value'))->default(WhoseMove::None->value);
            $table->timestamp('whose_move_since')->useCurrent();
            $table->dateTime('next_meeting_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();

            $table->index(['archived_at', 'whose_move']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
