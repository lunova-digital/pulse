<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tasks live on a To Do / In Progress / Complete board; the daily recurrence rule is gone. */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status')->default('todo')->after('title');
            $table->index(['project_id', 'status']);
        });

        DB::table('tasks')->whereNotNull('completed_at')->update(['status' => 'done']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('recurrence');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence')->nullable()->after('is_high');
            $table->dropIndex(['project_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
