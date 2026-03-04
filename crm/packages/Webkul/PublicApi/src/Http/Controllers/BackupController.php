<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class BackupController extends Controller
{
    /**
     * List all backups.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('backups')->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $backups = $query->paginate($request->input('per_page', 15));

        return response()->json($backups);
    }

    /**
     * Create a new backup.
     */
    public function store(Request $request): JsonResponse
    {
        $disk = $request->input('disk', 'local');

        $exitCode = Artisan::call('backup:database', ['--disk' => $disk]);

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Backup failed.',
                'output'  => Artisan::output(),
            ], 500);
        }

        $latest = DB::table('backups')->orderByDesc('id')->first();

        return response()->json([
            'data'    => $latest,
            'message' => 'Backup created successfully.',
        ], 201);
    }

    /**
     * Show a specific backup.
     */
    public function show(int $id): JsonResponse
    {
        $backup = DB::table('backups')->where('id', $id)->first();

        if (! $backup) {
            return response()->json(['message' => 'Backup not found'], 404);
        }

        return response()->json(['data' => $backup]);
    }

    /**
     * Delete a backup.
     */
    public function destroy(int $id): JsonResponse
    {
        $backup = DB::table('backups')->where('id', $id)->first();

        if (! $backup) {
            return response()->json(['message' => 'Backup not found'], 404);
        }

        // Delete the file
        $filePath = storage_path("app/{$backup->path}");
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        DB::table('backups')->where('id', $id)->delete();

        return response()->json(['message' => 'Backup deleted.']);
    }
}
