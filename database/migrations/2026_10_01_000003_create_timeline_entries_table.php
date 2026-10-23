<?php

use App\Enums\EntryType;
use App\Enums\WhoseMove;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->enum('type', array_column(EntryType::cases(), 'value'));
            $table->string('status_label')->nullable();
            $table->text('note')->nullable();
            $table->enum('whose_move', array_column(WhoseMove::cases(), 'value'))->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_entries');
    }
};
