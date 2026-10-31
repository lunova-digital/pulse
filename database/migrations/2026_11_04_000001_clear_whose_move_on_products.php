<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Products have nobody to wait on: whose move only applies to client projects. */
    public function up(): void
    {
        DB::table('projects')->where('kind', 'product')->update(['whose_move' => 'none']);
    }

    public function down(): void {}
};
