<x-admin::layouts>
    <x-slot:title>
        Import Leads
    </x-slot>

    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <x-admin::breadcrumbs name="leads" />
            <div class="text-xl font-bold dark:text-white">Import Leads</div>
        </div>
    </div>

    <div class="mt-4 flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bulk import from CSV / XLSX</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Drop a CSV or Excel file in the Datalot column format. One lead and one contact will be created per row.
                Need the format? <a href="{{ route('admin.leads.import.template') }}" class="text-brandColor underline" data-testid="leads-import-template">Download the template</a>.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded border border-green-300 bg-green-50 p-3 text-sm text-green-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300" data-testid="leads-import-success">
                {{ session('success') }}
                @if (! empty(session('import_errors')))
                    <ul class="mt-2 list-disc pl-5 text-xs">
                        @foreach (session('import_errors') as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @if (session('error'))
            <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300" data-testid="leads-import-error">
                {{ session('error') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('admin.leads.import.process') }}"
            enctype="multipart/form-data"
            class="flex flex-col gap-4"
        >
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Lead file (.csv, .xlsx, .xls — max 10 MB)
                </label>
                <input
                    type="file"
                    name="file"
                    required
                    accept=".csv,.xlsx,.xls"
                    data-testid="leads-import-file"
                    class="mt-1 block w-full rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                />
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="primary-button" data-testid="leads-import-submit">Upload &amp; Import</button>
                <a href="{{ route('admin.leads.index') }}" class="secondary-button">Cancel</a>
            </div>
        </form>

        <div class="mt-2 rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
            <p class="mb-2 font-semibold">Expected columns (in any order):</p>
            <code class="block overflow-x-auto whitespace-pre rounded bg-white p-2 text-xs dark:bg-gray-900">first_name, last_name, email, phone, street_address, city, state, zip, date_of_birth, vertical, lead_cost, lead_id, notes</code>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-xs">
                <li><strong>vertical</strong> maps to Lead Type: <em>auto/home/personal → Personal</em>, <em>commercial/business → Commercial</em>, <em>life/health → Life/Health</em>, <em>cross-sell → Cross-sell</em>.</li>
                <li><strong>lead_cost</strong> populates the lead's premium / lead_value.</li>
                <li><strong>lead_id</strong> is preserved in the lead's notes for traceability back to Datalot.</li>
                <li>Lead source is set to <strong>Datalot</strong> automatically (created on first import).</li>
            </ul>
        </div>
    </div>
</x-admin::layouts>
