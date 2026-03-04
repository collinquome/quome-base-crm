<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Models\Organization;
use Webkul\Lead\Models\Lead;

class TrashController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all');
        $perPage = min((int) $request->get('per_page', 15), 100);
        $result = [];

        if (in_array($type, ['all', 'contacts'])) {
            $contacts = Person::onlyTrashed()
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);
            if ($type === 'contacts') {
                return response()->json($contacts);
            }
            $result['contacts'] = $contacts->items();
        }

        if (in_array($type, ['all', 'leads'])) {
            $leads = Lead::onlyTrashed()
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);
            if ($type === 'leads') {
                return response()->json($leads);
            }
            $result['leads'] = $leads->items();
        }

        if (in_array($type, ['all', 'organizations'])) {
            $orgs = Organization::onlyTrashed()
                ->orderBy('deleted_at', 'desc')
                ->paginate($perPage);
            if ($type === 'organizations') {
                return response()->json($orgs);
            }
            $result['organizations'] = $orgs->items();
        }

        return response()->json(['data' => $result]);
    }

    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        $model = match ($type) {
            'contacts'      => Person::onlyTrashed()->findOrFail($id),
            'leads'         => Lead::onlyTrashed()->findOrFail($id),
            'organizations' => Organization::onlyTrashed()->findOrFail($id),
            default         => abort(422, 'Invalid type'),
        };

        $model->restore();

        return response()->json(['data' => $model->fresh(), 'message' => ucfirst($type) . ' restored.']);
    }

    public function forceDelete(Request $request, string $type, int $id): JsonResponse
    {
        $model = match ($type) {
            'contacts'      => Person::onlyTrashed()->findOrFail($id),
            'leads'         => Lead::onlyTrashed()->findOrFail($id),
            'organizations' => Organization::onlyTrashed()->findOrFail($id),
            default         => abort(422, 'Invalid type'),
        };

        $model->forceDelete();

        return response()->json(['message' => ucfirst($type) . ' permanently deleted.']);
    }
}
