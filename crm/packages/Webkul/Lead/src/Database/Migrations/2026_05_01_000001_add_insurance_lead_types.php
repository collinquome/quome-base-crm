<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $types = ['Personal', 'Commercial', 'Cross-sell', 'Life/Health'];

    public function up(): void
    {
        $now = Carbon::now();

        foreach ($this->types as $name) {
            if (! DB::table('lead_types')->where('name', $name)->exists()) {
                DB::table('lead_types')->insert([
                    'name'       => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $fallback = DB::table('lead_types')->where('name', 'Lead')->value('id') ?? 1;

        $removeIds = DB::table('lead_types')
            ->whereIn('name', $this->types)
            ->pluck('id');

        if ($removeIds->isNotEmpty()) {
            DB::table('leads')
                ->whereIn('lead_type_id', $removeIds)
                ->update(['lead_type_id' => $fallback]);

            DB::table('lead_types')->whereIn('id', $removeIds)->delete();
        }
    }
};
