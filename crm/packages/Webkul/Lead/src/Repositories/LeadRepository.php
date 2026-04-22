<?php

namespace Webkul\Lead\Repositories;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Contracts\Lead;

class LeadRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'title',
        'lead_value',
        'status',
        'user_id',
        'user.name',
        'person_id',
        'person.name',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'created_at',
        'closed_at',
        'expected_close_date',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected StageRepository $stageRepository,
        protected PersonRepository $personRepository,
        protected ProductRepository $productRepository,
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Lead::class;
    }

    /**
     * Get leads query.
     *
     * @param  int  $pipelineId
     * @param  int  $pipelineStageId
     * @param  string  $term
     * @param  string  $createdAtRange
     * @return mixed
     */
    public function getLeadsQuery($pipelineId, $pipelineStageId, $term, $createdAtRange)
    {
        return $this->with([
            'attribute_values',
            'pipeline',
            'stage',
        ])->scopeQuery(function ($query) use ($pipelineId, $pipelineStageId, $term, $createdAtRange) {
            return $query->select(
                'leads.id as id',
                'leads.created_at as created_at',
                'title',
                'lead_value',
                'persons.name as person_name',
                'leads.person_id as person_id',
                'lead_pipelines.id as lead_pipeline_id',
                'lead_pipeline_stages.name as status',
                'lead_pipeline_stages.id as lead_pipeline_stage_id'
            )
                ->addSelect(DB::raw('DATEDIFF('.DB::getTablePrefix().'leads.created_at + INTERVAL lead_pipelines.rotten_days DAY, now()) as rotten_days'))
                ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                ->leftJoin('lead_pipelines', 'leads.lead_pipeline_id', '=', 'lead_pipelines.id')
                ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
                ->where('title', 'like', "%$term%")
                ->where('leads.lead_pipeline_id', $pipelineId)
                ->where('leads.lead_pipeline_stage_id', $pipelineStageId)
                ->when($createdAtRange, function ($query) use ($createdAtRange) {
                    return $query->whereBetween('leads.created_at', $createdAtRange);
                })
                ->where(function ($query) {
                    if ($userIds = bouncer()->getAuthorizedUserIds()) {
                        $query->whereIn('leads.user_id', $userIds);
                    }
                });
        });
    }

    /**
     * Decode the `addresses_payload` JSON string emitted by the repeater
     * on the lead-create contact block into the `addresses` array the
     * Person model's fillable + cast expect. Filters empty rows.
     */
    protected function resolvePersonAddressesPayload(array $person): array
    {
        if (! array_key_exists('addresses_payload', $person)) {
            return $person;
        }

        $decoded = json_decode((string) $person['addresses_payload'], true);

        $person['addresses'] = is_array($decoded)
            ? array_values(array_filter(
                $decoded,
                fn ($a) => is_array($a) && array_filter($a, fn ($v) => $v !== null && $v !== '')
            ))
            : [];

        unset($person['addresses_payload']);

        return $person;
    }

    /**
     * Create.
     *
     * @return \Webkul\Lead\Contracts\Lead
     */
    public function create(array $data)
    {
        /**
         * If a person is provided, create or update the person and set the `person_id`.
         * Inherit the lead's user_id (falling back to the auth user) so Individual /
         * Group-scoped users can see the contact they just created as part of the lead.
         */
        if (isset($data['person'])) {
            $data['person'] = $this->resolvePersonAddressesPayload($data['person']);

            if (! empty($data['person']['id'])) {
                $person = $this->personRepository->findOrFail($data['person']['id']);
            } else {
                $ownerId = $data['user_id']
                    ?? ($data['person']['user_id'] ?? null)
                    ?? auth()->guard('user')->id();

                $person = $this->personRepository->create(array_merge($data['person'], [
                    'entity_type' => 'persons',
                    'user_id'     => $ownerId,
                ]));
            }

            $data['person_id'] = $person->id;
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
        }

        $lead = parent::create(array_merge([
            'lead_pipeline_id'       => 1,
            'lead_pipeline_stage_id' => 1,
        ], $data));

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $lead->id,
        ]));

        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                $this->productRepository->create(array_merge($product, [
                    'lead_id' => $lead->id,
                    'amount'  => $product['price'] * $product['quantity'],
                ]));
            }
        }

        return $lead;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  array|\Illuminate\Database\Eloquent\Collection  $attributes
     * @return \Webkul\Lead\Contracts\Lead
     */
    public function update(array $data, $id, $attributes = [])
    {
        /**
         * If a person is provided, create or update the person and set the `person_id`.
         * Be cautious, as a lead can be updated without providing person data.
         * For example, in the lead Kanban section, when switching stages, only the stage will be updated.
         */
        if (isset($data['person'])) {
            $data['person'] = $this->resolvePersonAddressesPayload($data['person']);

            if (! empty($data['person']['id'])) {
                $person = $this->personRepository->findOrFail($data['person']['id']);
            } else {
                $ownerId = $data['user_id']
                    ?? ($data['person']['user_id'] ?? null)
                    ?? auth()->guard('user')->id();

                $person = $this->personRepository->create(array_merge($data['person'], [
                    'entity_type' => 'persons',
                    'user_id'     => $ownerId,
                ]));
            }

            $data['person_id'] = $person->id;
        }

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->find($data['lead_pipeline_stage_id']);

            if (in_array($stage->code, ['won', 'lost'])) {
                $data['closed_at'] = $data['closed_at'] ?? Carbon::now();
            } else {
                $data['closed_at'] = null;
            }
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
        }

        $lead = parent::update($data, $id);

        /**
         * If attributes are provided, only save the provided attributes and return.
         * A collection of attributes may also be provided, which will be treated as valid,
         * regardless of whether it is empty or not.
         */
        if (! empty($attributes)) {
            /**
             * If attributes are provided as an array, then fetch the attributes from the database;
             * otherwise, use the provided collection of attributes.
             */
            if (is_array($attributes)) {
                $conditions = ['entity_type' => $data['entity_type']];

                if (isset($data['quick_add'])) {
                    $conditions['quick_add'] = 1;
                }

                $attributes = $this->attributeRepository->where($conditions)
                    ->whereIn('code', $attributes)
                    ->get();
            }

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $lead->id,
            ]), $attributes);

            return $lead;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $lead->id,
        ]));

        $previousProductIds = $lead->products()->pluck('id');

        if (isset($data['products'])) {
            foreach ($data['products'] as $productId => $productInputs) {
                if (Str::contains($productId, 'product_')) {
                    $this->productRepository->create(array_merge([
                        'lead_id' => $lead->id,
                    ], $productInputs));
                } else {
                    if (is_numeric($index = $previousProductIds->search($productId))) {
                        $previousProductIds->forget($index);
                    }

                    $this->productRepository->update($productInputs, $productId);
                }
            }
        }

        foreach ($previousProductIds as $productId) {
            $this->productRepository->delete($productId);
        }

        return $lead;
    }
}
