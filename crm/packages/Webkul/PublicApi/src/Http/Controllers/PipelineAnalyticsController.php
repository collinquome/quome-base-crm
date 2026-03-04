<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;

class PipelineAnalyticsController extends Controller
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository
    ) {}

    public function forecast(Request $request): JsonResponse
    {
        $pipelineId = $request->get('pipeline_id');
        $period = $request->get('period', 'month'); // month, quarter, year

        $query = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.status', 1) // active leads
            ->whereNotIn('lead_pipeline_stages.code', ['won', 'lost']);

        if ($pipelineId) {
            $query->where('leads.lead_pipeline_id', $pipelineId);
        }

        // Calculate weighted forecast
        $stages = $query->select(
            'lead_pipeline_stages.id as stage_id',
            'lead_pipeline_stages.name as stage_name',
            'lead_pipeline_stages.sort_order',
            DB::raw('COUNT(leads.id) as deal_count'),
            DB::raw('SUM(COALESCE(leads.lead_value, 0)) as total_value'),
            DB::raw('AVG(COALESCE(leads.lead_value, 0)) as avg_value')
        )
        ->groupBy('lead_pipeline_stages.id', 'lead_pipeline_stages.name', 'lead_pipeline_stages.sort_order')
        ->orderBy('lead_pipeline_stages.sort_order')
        ->get();

        // Assign probability based on stage position
        $totalStages = $stages->count();
        $weightedTotal = 0;
        $unwieghtedTotal = 0;

        $stageData = $stages->map(function ($stage, $index) use ($totalStages, &$weightedTotal, &$unwieghtedTotal) {
            // Simple probability: later stages = higher probability
            $probability = $totalStages > 0 ? round(($index + 1) / $totalStages * 100) : 0;
            $weighted = $stage->total_value * ($probability / 100);
            $weightedTotal += $weighted;
            $unwieghtedTotal += $stage->total_value;

            return [
                'stage_id'    => $stage->stage_id,
                'stage_name'  => $stage->stage_name,
                'deal_count'  => $stage->deal_count,
                'total_value' => round($stage->total_value, 2),
                'avg_value'   => round($stage->avg_value, 2),
                'probability' => $probability,
                'weighted_value' => round($weighted, 2),
            ];
        });

        // Get won deals for comparison
        $wonQuery = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->where('lead_pipeline_stages.code', 'won');

        if ($pipelineId) {
            $wonQuery->where('leads.lead_pipeline_id', $pipelineId);
        }

        $now = Carbon::now();
        $periodStart = match ($period) {
            'quarter' => $now->copy()->startOfQuarter(),
            'year'    => $now->copy()->startOfYear(),
            default   => $now->copy()->startOfMonth(),
        };

        $wonThisPeriod = (clone $wonQuery)
            ->where('leads.closed_at', '>=', $periodStart)
            ->sum('leads.lead_value');

        $wonTotal = $wonQuery->sum('leads.lead_value');

        return response()->json([
            'data' => [
                'stages'          => $stageData,
                'forecast_total'  => round($weightedTotal, 2),
                'pipeline_total'  => round($unwieghtedTotal, 2),
                'won_this_period' => round($wonThisPeriod, 2),
                'won_all_time'    => round($wonTotal, 2),
                'period'          => $period,
            ],
        ]);
    }

    public function velocity(Request $request): JsonResponse
    {
        $pipelineId = $request->get('pipeline_id');

        // Calculate avg time in each stage from stage history
        $historyQuery = DB::table('lead_stage_history')
            ->join('lead_pipeline_stages', 'lead_stage_history.stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNotNull('lead_stage_history.exited_at');

        if ($pipelineId) {
            $historyQuery->where('lead_stage_history.pipeline_id', $pipelineId);
        }

        $stageVelocity = $historyQuery->select(
            'lead_pipeline_stages.id as stage_id',
            'lead_pipeline_stages.name as stage_name',
            'lead_pipeline_stages.sort_order',
            DB::raw('AVG(TIMESTAMPDIFF(HOUR, lead_stage_history.entered_at, lead_stage_history.exited_at)) / 24 as avg_days'),
            DB::raw('MIN(TIMESTAMPDIFF(HOUR, lead_stage_history.entered_at, lead_stage_history.exited_at)) / 24 as min_days'),
            DB::raw('MAX(TIMESTAMPDIFF(HOUR, lead_stage_history.entered_at, lead_stage_history.exited_at)) / 24 as max_days'),
            DB::raw('COUNT(*) as sample_count')
        )
        ->groupBy('lead_pipeline_stages.id', 'lead_pipeline_stages.name', 'lead_pipeline_stages.sort_order')
        ->orderBy('lead_pipeline_stages.sort_order')
        ->get()
        ->map(function ($stage) {
            return [
                'stage_id'     => $stage->stage_id,
                'stage_name'   => $stage->stage_name,
                'avg_days'     => round($stage->avg_days, 1),
                'min_days'     => round($stage->min_days, 1),
                'max_days'     => round($stage->max_days, 1),
                'sample_count' => $stage->sample_count,
            ];
        });

        // Overall average deal cycle time
        $overallQuery = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->whereIn('lead_pipeline_stages.code', ['won', 'lost'])
            ->whereNotNull('leads.closed_at');

        if ($pipelineId) {
            $overallQuery->where('leads.lead_pipeline_id', $pipelineId);
        }

        $avgCycleDays = $overallQuery
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, leads.created_at, leads.closed_at)) / 24 as avg_cycle_days'))
            ->value('avg_cycle_days');

        // Identify bottleneck (stage with longest avg time)
        $bottleneck = $stageVelocity->sortByDesc('avg_days')->first();

        return response()->json([
            'data' => [
                'stages'          => $stageVelocity->values(),
                'avg_cycle_days'  => round($avgCycleDays ?? 0, 1),
                'bottleneck'      => $bottleneck,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $pipelineId = $request->get('pipeline_id');

        $baseQuery = DB::table('leads')->whereNull('leads.deleted_at');
        if ($pipelineId) {
            $baseQuery->where('leads.lead_pipeline_id', $pipelineId);
        }

        $totalLeads = (clone $baseQuery)->count();
        $activeLeads = (clone $baseQuery)->where('status', 1)->count();
        $totalValue = (clone $baseQuery)->where('status', 1)->sum('lead_value');

        $wonLeads = (clone $baseQuery)
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('lead_pipeline_stages.code', 'won')
            ->count();

        $lostLeads = (clone $baseQuery)
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('lead_pipeline_stages.code', 'lost')
            ->count();

        $winRate = ($wonLeads + $lostLeads) > 0
            ? round($wonLeads / ($wonLeads + $lostLeads) * 100, 1)
            : 0;

        return response()->json([
            'data' => [
                'total_leads'  => $totalLeads,
                'active_leads' => $activeLeads,
                'total_value'  => round($totalValue, 2),
                'won_leads'    => $wonLeads,
                'lost_leads'   => $lostLeads,
                'win_rate'     => $winRate,
            ],
        ]);
    }
}
