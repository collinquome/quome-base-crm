<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'title')
            ->update(['name' => 'Name']);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'title')
            ->update(['name' => 'Title']);
    }
};
