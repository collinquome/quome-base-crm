<x-admin::layouts>
    <x-slot:title>
        Report Builder
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    Report Builder
                </div>
                <p class="text-gray-600 dark:text-gray-400">Create custom reports from your CRM data</p>
            </div>
        </div>

        <v-report-builder></v-report-builder>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-report-builder-template"
        >
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Left Panel: Configuration -->
                <div class="lg:col-span-1" data-testid="report-builder-config">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-4 text-lg font-semibold dark:text-white">Configure Report</h3>

                        <!-- Report Name -->
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Report Name</label>
                            <input
                                v-model="report.name"
                                type="text"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="My Custom Report"
                                data-testid="report-name-input"
                            >
                        </div>

                        <!-- Entity Type -->
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Entity Type</label>
                            <select
                                v-model="report.entity_type"
                                @change="onEntityTypeChange"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                data-testid="report-entity-select"
                            >
                                <option value="">Select Entity</option>
                                <option v-for="entity in entities" :key="entity.type" :value="entity.type" v-text="entity.label"></option>
                            </select>
                        </div>

                        <!-- Columns -->
                        <div class="mb-4" v-if="availableColumns.length">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Columns</label>
                            <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-2 dark:border-gray-700" data-testid="report-columns">
                                <label
                                    v-for="col in availableColumns"
                                    :key="col.key"
                                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    <input
                                        type="checkbox"
                                        :value="col.key"
                                        v-model="report.columns"
                                        class="rounded border-gray-300"
                                    >
                                    <span v-text="col.label"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Group By -->
                        <div class="mb-4" v-if="availableColumns.length">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Group By</label>
                            <select
                                v-model="report.group_by"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                data-testid="report-groupby-select"
                            >
                                <option value="">None</option>
                                <option v-for="col in availableColumns" :key="col.key" :value="col.key" v-text="col.label"></option>
                            </select>
                        </div>

                        <!-- Chart Type -->
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Chart Type</label>
                            <div class="flex flex-wrap gap-2" data-testid="report-chart-types">
                                <button
                                    v-for="type in chartTypes"
                                    :key="type.value"
                                    type="button"
                                    class="rounded-md border px-3 py-1.5 text-sm transition-all"
                                    :class="report.chart_type === type.value ? 'border-brandColor bg-brandColor text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                    @click="report.chart_type = type.value"
                                    v-text="type.label"
                                ></button>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Filters</label>

                            <div v-for="(filter, idx) in report.filters" :key="idx" class="mb-2 flex items-center gap-2">
                                <select
                                    v-model="filter.column"
                                    class="flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option value="">Column</option>
                                    <option v-for="col in availableColumns" :key="col.key" :value="col.key" v-text="col.label"></option>
                                </select>
                                <select
                                    v-model="filter.operator"
                                    class="w-20 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option value="=">=</option>
                                    <option value="!=">!=</option>
                                    <option value=">">></option>
                                    <option value="<"><</option>
                                    <option value="like">like</option>
                                </select>
                                <input
                                    v-model="filter.value"
                                    class="flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    placeholder="Value"
                                >
                                <button
                                    type="button"
                                    class="text-red-500 hover:text-red-700"
                                    @click="report.filters.splice(idx, 1)"
                                >
                                    <span class="icon-delete text-lg"></span>
                                </button>
                            </div>

                            <button
                                type="button"
                                class="text-sm text-brandColor hover:underline"
                                @click="report.filters.push({ column: '', operator: '=', value: '' })"
                                data-testid="report-add-filter-btn"
                            >
                                + Add Filter
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="primary-button flex-1"
                                @click="previewReport"
                                :disabled="!canPreview"
                                data-testid="report-preview-btn"
                            >
                                Preview
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                                @click="saveReport"
                                :disabled="!canSave"
                                data-testid="report-save-btn"
                            >
                                Save Report
                            </button>
                        </div>
                    </div>

                    <!-- Saved Reports List -->
                    <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" data-testid="report-saved-list">
                        <h3 class="mb-3 text-lg font-semibold dark:text-white">Saved Reports</h3>
                        <div v-if="savedReports.length === 0" class="text-sm text-gray-400">No saved reports yet</div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="saved in savedReports"
                                :key="saved.id"
                                class="flex cursor-pointer items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                @click="loadReport(saved)"
                            >
                                <div>
                                    <span class="font-medium dark:text-white" v-text="saved.name"></span>
                                    <span class="ml-2 text-xs text-gray-400" v-text="saved.entity_type"></span>
                                </div>
                                <span class="text-xs text-gray-400" v-text="saved.chart_type || 'table'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Preview -->
                <div class="lg:col-span-2" data-testid="report-builder-preview">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-4 text-lg font-semibold dark:text-white">Preview</h3>

                        <!-- No Preview -->
                        <div v-if="!previewData" class="flex h-64 items-center justify-center text-gray-400" data-testid="report-no-preview">
                            <div class="text-center">
                                <span class="icon-note text-5xl"></span>
                                <p class="mt-2">Configure your report and click Preview</p>
                            </div>
                        </div>

                        <!-- Loading -->
                        <div v-else-if="isPreviewLoading" class="flex h-64 items-center justify-center">
                            <span class="animate-spin icon-processing text-3xl text-brandColor"></span>
                        </div>

                        <!-- Preview Results -->
                        <div v-else>
                            <!-- Summary -->
                            <div class="mb-4 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                <span>@{{ previewData.length }} records</span>
                                <span v-if="report.chart_type">Chart: @{{ report.chart_type }}</span>
                            </div>

                            <!-- Table View -->
                            <div class="overflow-x-auto" data-testid="report-preview-table">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th
                                                v-for="col in report.columns"
                                                :key="col"
                                                class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400"
                                                v-text="getColumnLabel(col)"
                                            ></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, idx) in previewData.slice(0, 50)"
                                            :key="idx"
                                            class="border-b border-gray-100 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                        >
                                            <td
                                                v-for="col in report.columns"
                                                :key="col"
                                                class="px-3 py-2 dark:text-gray-300"
                                                v-html="formatCellValue(row[col])"
                                            ></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p v-if="previewData.length > 50" class="mt-2 text-center text-sm text-gray-400">
                                    Showing 50 of @{{ previewData.length }} records
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-report-builder', {
                template: '#v-report-builder-template',

                data() {
                    return {
                        entities: [],
                        report: {
                            name: '',
                            entity_type: '',
                            columns: [],
                            filters: [],
                            group_by: '',
                            chart_type: 'table',
                        },
                        chartTypes: [
                            { value: 'table', label: 'Table' },
                            { value: 'bar', label: 'Bar' },
                            { value: 'line', label: 'Line' },
                            { value: 'pie', label: 'Pie' },
                        ],
                        savedReports: [],
                        previewData: null,
                        isPreviewLoading: false,
                    };
                },

                computed: {
                    availableColumns() {
                        if (!this.report.entity_type) return [];
                        const entity = this.entities.find(e => e.type === this.report.entity_type);
                        return entity?.columns || [];
                    },

                    canPreview() {
                        return this.report.entity_type && this.report.columns.length > 0;
                    },

                    canSave() {
                        return this.report.name && this.report.entity_type && this.report.columns.length > 0;
                    },
                },

                mounted() {
                    this.fetchSchema();
                    this.fetchSavedReports();
                },

                methods: {
                    async fetchSchema() {
                        try {
                            const response = await this.$axios.get('/api/v1/reports/schema');
                            const data = response.data?.data || {};

                            // Schema returns {entity_type: {columns: [...]}} object
                            if (Array.isArray(data)) {
                                this.entities = data;
                            } else {
                                this.entities = Object.entries(data).map(([type, info]) => ({
                                    type,
                                    label: type.charAt(0).toUpperCase() + type.slice(1),
                                    columns: (info.columns || []).map(c => typeof c === 'string' ? { key: c, label: c.replace(/_/g, ' ') } : c),
                                }));
                            }
                        } catch (error) {
                            console.error('Failed to fetch schema:', error);
                        }
                    },

                    async fetchSavedReports() {
                        try {
                            const response = await this.$axios.get('/api/v1/reports');
                            this.savedReports = response.data?.data || [];
                        } catch (error) {
                            console.error('Failed to fetch saved reports:', error);
                        }
                    },

                    onEntityTypeChange() {
                        this.report.columns = [];
                        this.report.group_by = '';
                        this.report.filters = [];
                        this.previewData = null;
                    },

                    async previewReport() {
                        this.isPreviewLoading = true;
                        this.previewData = null;

                        try {
                            const response = await this.$axios.post('/api/v1/reports/execute', {
                                entity_type: this.report.entity_type,
                                columns: this.report.columns,
                                filters: this.report.filters.filter(f => f.column && f.value),
                                group_by: this.report.group_by || null,
                            });
                            this.previewData = response.data?.data || [];
                        } catch (error) {
                            console.error('Failed to preview report:', error);
                            this.previewData = [];
                        } finally {
                            this.isPreviewLoading = false;
                        }
                    },

                    async saveReport() {
                        try {
                            const response = await this.$axios.post('/api/v1/reports', {
                                name: this.report.name,
                                entity_type: this.report.entity_type,
                                columns: this.report.columns,
                                filters: this.report.filters.filter(f => f.column && f.value),
                                group_by: this.report.group_by || null,
                                chart_type: this.report.chart_type,
                            });

                            this.fetchSavedReports();

                            if (window.$emitter) {
                                window.$emitter.emit('add-flash', { type: 'success', message: 'Report saved.' });
                            }
                        } catch (error) {
                            console.error('Failed to save report:', error);
                        }
                    },

                    loadReport(saved) {
                        this.report = {
                            name: saved.name,
                            entity_type: saved.entity_type,
                            columns: saved.columns || [],
                            filters: saved.filters || [],
                            group_by: saved.group_by || '',
                            chart_type: saved.chart_type || 'table',
                        };
                        this.previewData = null;
                    },

                    getColumnLabel(key) {
                        const col = this.availableColumns.find(c => c.key === key);
                        return col?.label || key.replace(/_/g, ' ');
                    },

                    formatCellValue(value) {
                        if (value === null || value === undefined) return '-';
                        if (Array.isArray(value)) return value.join(', ');
                        if (typeof value === 'object') return JSON.stringify(value);
                        return String(value);
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
