<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
            $table->boolean('can_manage_clients')->default(false)->after('role');
            $table->boolean('is_active')->default(true)->after('can_manage_clients');
        });

        // Everyone who existed before teams were introduced was the single owner.
        DB::table('users')->update(['role' => 'owner', 'can_manage_clients' => true]);

        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'user_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('kind')->default('project')->after('name');
            $table->date('priority_for')->nullable()->after('is_pinned');
            $table->index(['archived_at', 'priority_for']);
        });

        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->boolean('visible_to_client')->default(true)->after('whose_move');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->boolean('is_high')->default(false)->after('due_date');
        });

        Schema::create('daily_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('skipped_on');
            $table->boolean('was_high')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->unique(['project_id', 'skipped_on']);
            $table->unique(['task_id', 'skipped_on']);
            $table->index('skipped_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_skips');
    }
};
