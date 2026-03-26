<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')
            ->where('code', 'lead_value')
            ->update(['name' => 'Premium']);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('code', 'lead_value')
            ->update(['name' => 'Lead Value']);
    }
};
