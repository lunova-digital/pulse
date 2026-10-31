<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A pin is now the daily priority itself and carries over until changed, so the dated column goes. */
    public function up(): void
    {
        DB::table('projects')->whereDate('priority_for', '>=', today()->toDateString())->update(['is_pinned' => true]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'priority_for']);
            $table->dropColumn('priority_for');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('priority_for')->nullable()->after('is_pinned');
            $table->index(['archived_at', 'priority_for']);
        });
    }
};
