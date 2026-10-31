<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        $now = now();
        foreach (['Lead', 'Contacted', 'Meeting', 'Proposal', 'Negotiation', 'Won', 'Lost'] as $i => $name) {
            DB::table('pipeline_stages')->insert([
                'name' => $name, 'position' => $i,
                'is_won' => $name === 'Won', 'is_lost' => $name === 'Lost',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->dateTime('next_followup_at')->nullable();
            $table->boolean('followup_has_time')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index('next_followup_at');
        });

        Schema::create('client_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('note')->nullable();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->restrictOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('pipeline_stages')->restrictOnDelete();
            $table->dateTime('next_followup_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('client_name')->constrained()->restrictOnDelete();
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->dateTime('sent_for');
            $table->timestamp('created_at')->nullable();
            $table->unique(['client_id', 'kind', 'sent_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('client_entries');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('pipeline_stages');
    }
};
