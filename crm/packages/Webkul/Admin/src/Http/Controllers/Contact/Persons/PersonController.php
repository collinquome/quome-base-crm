<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Services\PostHogService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Contact\Repositories\PersonRepository;

class PersonController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(protected PersonRepository $personRepository)
    {
        request()->request->add(['entity_type' => 'persons']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PersonDataGrid::class)->process();
        }

        return view('admin::contacts.persons.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::contacts.persons.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.person.create.before');

        $person = $this->personRepository->create($this->resolveAddressesPayload($request->all()));

        Event::dispatch('contacts.person.create.after', $person);

        PostHogService::capture(PostHogService::distinctId(), 'contact_created', [
            'contact_id'   => $person->id,
            'contact_name' => $person->name,
        ]);

        if (request()->ajax()) {
            return response()->json([
                'data'    => $person,
                'message' => trans('admin::app.contacts.persons.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.create-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $person = $this->personRepository->findOrFail($id);

        return view('admin::contacts.persons.view', compact('person'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $person = $this->personRepository->findOrFail($id);

        return view('admin::contacts.persons.edit', compact('person'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.person.update.before', $id);

        $person = $this->personRepository->update($this->resolveAddressesPayload($request->all()), $id);

        Event::dispatch('contacts.person.update.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data'    => $person,
                'message' => trans('admin::app.contacts.persons.index.update-success'),
            ], 200);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.update-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Search person results.
     *
     * Supports both the Prettus RequestCriteria params (?search=&searchFields=)
     * and the lookup component's `?query=` param. The `query` path does a
     * case-insensitive LIKE against name, emails (JSON), and contact_numbers
     * (JSON) so that typing an email or phone surfaces the matching person.
     */
    public function search(): JsonResource
    {
        $term = trim((string) request()->input('query', ''));

        $query = $this->personRepository->getModel()->query();

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $query->whereIn('user_id', $userIds);
        }

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'LIKE', $like)
                    ->orWhereRaw("JSON_SEARCH(LOWER(JSON_UNQUOTE(emails)), 'one', ?) IS NOT NULL", [strtolower($like)])
                    ->orWhereRaw("JSON_SEARCH(LOWER(JSON_UNQUOTE(contact_numbers)), 'one', ?) IS NOT NULL", [strtolower($like)]);
            });
        } else {
            // No `?query=` — fall back to stock RequestCriteria behavior. The previous
            // implementation called pushCriteria(...) then immediately reached for
            // getModel()->query(), which discards the registered criteria entirely.
            // The result was that the mega-search "persons" tab silently ignored the
            // typed search term and returned the first 25 persons alphabetically — a
            // user typing "Fred" would see other names back and assume the search had
            // mistranslated their input. scopeQuery + all() preserves both criteria
            // and the bouncer scope.
            $userIds = bouncer()->getAuthorizedUserIds();

            $results = $this->personRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->scopeQuery(function ($q) use ($userIds) {
                    if ($userIds) {
                        $q = $q->whereIn('user_id', $userIds);
                    }
                    return $q->orderBy('name')->limit(25);
                })
                ->all();

            return PersonResource::collection($results);
        }

        return PersonResource::collection($query->orderBy('name')->take(25)->get());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $person = $this->personRepository->findOrFail($id);

        if (
            $person->leads
            && $person->leads->count() > 0
        ) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }

        try {
            Event::dispatch('contacts.person.delete.before', $person);

            $person->delete();

            Event::dispatch('contacts.person.delete.after', $person);

            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-success'),
            ], 200);

        } catch (Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Decode the `addresses_payload` JSON string emitted by the repeater
     * component into the `addresses` array that Person's fillable + cast
     * expect. Leaves data alone when the repeater wasn't on the form.
     *
     * @param  array  $data
     * @return array
     */
    protected function resolveAddressesPayload(array $data): array
    {
        if (array_key_exists('addresses_payload', $data)) {
            $decoded = json_decode((string) $data['addresses_payload'], true);

            $data['addresses'] = is_array($decoded)
                ? array_values(array_filter($decoded, fn ($a) => is_array($a) && array_filter($a, fn ($v) => $v !== null && $v !== '')))
                : [];

            unset($data['addresses_payload']);
        }

        return $data;
    }

    /**
     * Mass destroy the specified resources from storage.
     */
    public function massDestroy(MassDestroyRequest $request): JsonResponse
    {
        try {
            $persons = $this->personRepository->findWhereIn('id', $request->input('indices', []));

            $deletedCount = 0;

            $blockedCount = 0;

            foreach ($persons as $person) {
                if (
                    $person->leads
                    && $person->leads->count() > 0
                ) {
                    $blockedCount++;

                    continue;
                }

                Event::dispatch('contact.person.delete.before', $person);

                $this->personRepository->delete($person->id);

                Event::dispatch('contact.person.delete.after', $person);

                $deletedCount++;
            }

            $statusCode = 200;

            switch (true) {
                case $deletedCount > 0 && $blockedCount === 0:
                    $message = trans('admin::app.contacts.persons.index.all-delete-success');

                    break;

                case $deletedCount > 0 && $blockedCount > 0:
                    $message = trans('admin::app.contacts.persons.index.partial-delete-warning');

                    break;

                case $deletedCount === 0 && $blockedCount > 0:
                    $message = trans('admin::app.contacts.persons.index.none-delete-warning');

                    $statusCode = 400;

                    break;

                default:
                    $message = trans('admin::app.contacts.persons.index.no-selection');

                    $statusCode = 400;

                    break;
            }

            return response()->json(['message' => $message], $statusCode);
        } catch (Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }
    }
}
