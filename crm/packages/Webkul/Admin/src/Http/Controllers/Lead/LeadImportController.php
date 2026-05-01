<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;

/**
 * Datalot-style CSV/XLSX bulk lead import.
 *
 * Maps an insurance-lead-vendor export (Datalot or similar) onto the CRM's
 * Person + Lead pair. Built as a separate flow from the generic
 * data-transfer importer because the column shape is fixed and the
 * customer's flow is "drop a Datalot file in, get leads".
 */
class LeadImportController extends Controller
{
    /**
     * Headers in the downloadable template, in display order.
     */
    public const TEMPLATE_HEADERS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'street_address',
        'city',
        'state',
        'zip',
        'date_of_birth',
        'vertical',
        'lead_cost',
        'lead_id',
        'notes',
    ];

    public function __construct(
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {}

    public function show(): View
    {
        return view('admin::leads.import');
    }

    public function template(): StreamedResponse
    {
        $headers = self::TEMPLATE_HEADERS;
        // One example row so the user knows what shape we expect.
        $example = [
            'Jane', 'Doe', 'jane@example.com', '555-555-1234',
            '123 Main St', 'Bellevue', 'WA', '98005',
            '1985-04-12', 'auto', '12.50', 'DL-998877', 'Imported from Datalot',
        ];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        }, 'lead-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        $rows = $this->extractRows($request->file('file'));

        if (count($rows) < 2) {
            return back()->with('error', 'File appears empty. Need a header row plus at least one data row.');
        }

        $headers = array_map(
            fn ($h) => trim(strtolower((string) $h)),
            array_shift($rows)
        );

        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $defaultStageId = $pipeline?->stages?->sortBy('sort_order')->first()?->id;
        $userId = auth()->guard('user')->id();
        $datalotSourceId = $this->findOrCreateDatalotSource();

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                if ($this->isBlank($row)) {
                    continue;
                }

                $data = $this->mapRow($headers, $row);
                $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
                if ($name === '' && ! empty($data['name'])) {
                    $name = trim($data['name']);
                }

                if ($name === '') {
                    $skipped++;
                    $errors[] = 'Row '.($rowIndex + 2).': skipped (no name).';
                    continue;
                }

                $person = $this->personRepository->create([
                    'entity_type'     => 'persons', // required by attributeValueRepository->save()
                    'name'            => $name,
                    'user_id'         => $userId,
                    'emails'          => $this->emailsArray($data['email'] ?? null),
                    'contact_numbers' => $this->phonesArray($data['phone'] ?? null),
                    'addresses'       => $this->addressArray($data),
                    'organization_id' => null,
                ]);

                $leadTypeId = $this->resolveLeadTypeId($data['vertical'] ?? null);

                $title = $name;
                if (! empty($data['vertical'])) {
                    $title .= ' — '.ucfirst(trim($data['vertical']));
                }

                $notes = $this->buildNotes($data);

                $this->leadRepository->create([
                    'entity_type'            => 'leads', // required by attributeValueRepository->save()
                    'title'                  => $title,
                    'description'            => $notes,
                    'notes'                  => $notes,
                    'lead_value'             => $this->numeric($data['lead_cost'] ?? null),
                    'status'                 => 1,
                    'user_id'                => $userId,
                    'person_id'              => $person->id,
                    'lead_source_id'         => $datalotSourceId,
                    'lead_type_id'           => $leadTypeId,
                    'lead_pipeline_id'       => $pipeline?->id,
                    'lead_pipeline_stage_id' => $defaultStageId,
                ]);

                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        $message = "Imported {$created} lead(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} row(s).";
        }

        return redirect()
            ->route('admin.leads.index')
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    /**
     * Read the uploaded file into a 2-D array of strings.
     */
    protected function extractRows($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            // maatwebsite/excel ships with PhpSpreadsheet — use it directly so
            // we avoid the heavier facade for a one-shot read.
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());

            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        }

        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }

        return $rows;
    }

    protected function mapRow(array $headers, array $row): array
    {
        $out = [];
        foreach ($headers as $i => $h) {
            $out[$h] = isset($row[$i]) ? trim((string) $row[$i]) : '';
        }

        return $out;
    }

    protected function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function emailsArray(?string $email): array
    {
        $email = trim((string) $email);
        if ($email === '') {
            return [];
        }

        return [['value' => $email, 'label' => 'work']];
    }

    protected function phonesArray(?string $phone): array
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return [];
        }

        return [['value' => $phone, 'label' => 'work']];
    }

    protected function addressArray(array $data): array
    {
        $hasAny = false;
        foreach (['street_address', 'city', 'state', 'zip'] as $k) {
            if (! empty($data[$k])) {
                $hasAny = true;
                break;
            }
        }
        if (! $hasAny) {
            return [];
        }

        return [[
            'address' => $data['street_address'] ?? '',
            'city'    => $data['city'] ?? '',
            'state'   => $data['state'] ?? '',
            'postcode'=> $data['zip'] ?? '',
            'country' => $data['country'] ?? 'US',
            'label'   => 'home',
        ]];
    }

    protected function resolveLeadTypeId(?string $vertical): ?int
    {
        $vertical = strtolower(trim((string) $vertical));

        $byVertical = [
            'auto'      => 'Personal',
            'home'      => 'Personal',
            'personal'  => 'Personal',
            'commercial'=> 'Commercial',
            'business'  => 'Commercial',
            'life'      => 'Life/Health',
            'health'    => 'Life/Health',
            'life/health' => 'Life/Health',
            'cross-sell'=> 'Cross-sell',
            'crosssell' => 'Cross-sell',
        ];

        $name = $byVertical[$vertical] ?? 'Lead';

        return DB::table('lead_types')->where('name', $name)->value('id')
            ?? DB::table('lead_types')->where('name', 'Lead')->value('id');
    }

    protected function findOrCreateDatalotSource(): ?int
    {
        $existing = DB::table('lead_sources')->where('name', 'Datalot')->value('id');
        if ($existing) {
            return $existing;
        }

        $now = Carbon::now();

        return DB::table('lead_sources')->insertGetId([
            'name'       => 'Datalot',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function numeric(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

        return $cleaned === '' ? null : (float) $cleaned;
    }

    protected function buildNotes(array $data): string
    {
        $parts = [];
        if (! empty($data['lead_id'])) {
            $parts[] = 'Datalot Lead ID: '.$data['lead_id'];
        }
        if (! empty($data['date_of_birth'])) {
            $parts[] = 'DOB: '.$data['date_of_birth'];
        }
        if (! empty($data['notes'])) {
            $parts[] = $data['notes'];
        }

        return implode("\n", $parts);
    }
}
