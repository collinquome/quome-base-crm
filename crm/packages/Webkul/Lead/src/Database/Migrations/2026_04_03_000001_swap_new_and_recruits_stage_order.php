<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pipelineId = DB::table('lead_pipelines')->where('is_default', 1)->value('id');

        if (! $pipelineId) {
            return;
        }

        // Swap sort_order: Recruits becomes 1, New becomes 2
        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'recruits')
            ->update(['sort_order' => 1]);

        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'new')
            ->update(['sort_order' => 2]);
    }

    public function down(): void
    {
        $pipelineId = DB::table('lead_pipelines')->where('is_default', 1)->value('id');

        if (! $pipelineId) {
            return;
        }

        // Revert: New becomes 1, Recruits becomes 2
        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'new')
            ->update(['sort_order' => 1]);

        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'recruits')
            ->update(['sort_order' => 2]);
    }
};
