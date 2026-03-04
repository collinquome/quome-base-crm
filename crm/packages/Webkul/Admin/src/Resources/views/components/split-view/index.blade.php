@props([
    'listSrc' => '',
    'detailUrlPrefix' => '',
    'entityType' => 'record',
])

<v-split-view
    list-src="{{ $listSrc }}"
    detail-url-prefix="{{ $detailUrlPrefix }}"
    entity-type="{{ $entityType }}"
>
    <template #default>
        {{ $slot }}
    </template>
</v-split-view>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-split-view-template"
    >
        <div>
            <!-- View Toggle -->
            <div class="mb-3 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm transition-all"
                    :class="viewMode === 'full' ? 'bg-brandColor text-white' : 'border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                    @click="viewMode = 'full'; selectedId = null"
                    data-testid="split-view-full-btn"
                >
                    <span class="icon-listing text-lg"></span>
                    Full View
                </button>

                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm transition-all"
                    :class="viewMode === 'split' ? 'bg-brandColor text-white' : 'border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                    @click="viewMode = 'split'"
                    data-testid="split-view-split-btn"
                >
                    <span class="icon-leads text-lg"></span>
                    Split View
                </button>
            </div>

            <!-- Content Area -->
            <div
                class="flex gap-4"
                :class="{ 'flex-col': viewMode === 'full' }"
            >
                <!-- List Panel -->
                <div
                    :class="viewMode === 'split' ? 'w-1/2 overflow-auto border-r border-gray-200 pr-4 dark:border-gray-700' : 'w-full'"
                    :style="viewMode === 'split' ? 'max-height: calc(100vh - 200px)' : ''"
                >
                    <div @click="handleListClick($event)">
                        <slot></slot>
                    </div>
                </div>

                <!-- Detail Panel (Split View Only) -->
                <div
                    v-if="viewMode === 'split'"
                    class="w-1/2 overflow-auto"
                    style="max-height: calc(100vh - 200px)"
                    data-testid="split-view-detail-panel"
                >
                    <div v-if="!selectedId" class="flex h-64 items-center justify-center text-gray-400 dark:text-gray-500">
                        <div class="text-center">
                            <span class="icon-contact text-5xl"></span>
                            <p class="mt-2">Select a @{{ entityType }} to view details</p>
                        </div>
                    </div>

                    <div v-else-if="isLoadingDetail" class="flex h-64 items-center justify-center">
                        <span class="animate-spin icon-processing text-3xl text-brandColor"></span>
                    </div>

                    <div v-else>
                        <!-- Detail Header -->
                        <div class="mb-4 flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div>
                                <h3 class="text-lg font-semibold dark:text-white" v-text="detailData.name || detailData.title || 'Details'"></h3>
                                <p class="text-sm text-gray-500" v-if="detailData.email" v-text="detailData.email"></p>
                            </div>
                            <a
                                :href="detailUrlPrefix + '/' + selectedId"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                                target="_blank"
                            >
                                Open Full
                            </a>
                        </div>

                        <!-- Detail Body -->
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="grid grid-cols-2 gap-4">
                                <div
                                    v-for="(value, key) in filteredDetailData"
                                    :key="key"
                                    class="flex flex-col gap-1"
                                >
                                    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400" v-text="formatLabel(key)"></span>
                                    <span class="text-sm dark:text-gray-300" v-html="formatValue(value)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-split-view', {
            template: '#v-split-view-template',

            props: {
                listSrc: { type: String, default: '' },
                detailUrlPrefix: { type: String, default: '' },
                entityType: { type: String, default: 'record' },
            },

            data() {
                return {
                    viewMode: 'full',
                    selectedId: null,
                    isLoadingDetail: false,
                    detailData: {},
                };
            },

            computed: {
                filteredDetailData() {
                    const exclude = ['id', 'actions', 'password', 'token'];
                    const result = {};

                    for (const [key, value] of Object.entries(this.detailData)) {
                        if (! exclude.includes(key) && value !== null && value !== '' && value !== undefined) {
                            result[key] = value;
                        }
                    }

                    return result;
                },
            },

            methods: {
                handleListClick(event) {
                    if (this.viewMode !== 'split') return;

                    // Find the clicked row and extract the record ID
                    const row = event.target.closest('[class*="grid-rows"]');
                    if (! row) return;

                    // Look for the record ID in the row's first column
                    const idEl = row.querySelector('.flex.flex-col.gap-1\\.5');
                    if (idEl) {
                        const id = parseInt(idEl.textContent.trim());
                        if (id && id !== this.selectedId) {
                            this.selectedId = id;
                            this.loadDetail(id);
                        }
                    }
                },

                async loadDetail(id) {
                    this.isLoadingDetail = true;

                    try {
                        const response = await this.$axios.get(`/api/v1/${this.entityType}s/${id}`);
                        this.detailData = response.data?.data || response.data || {};
                    } catch (error) {
                        this.detailData = { error: 'Failed to load details' };
                    } finally {
                        this.isLoadingDetail = false;
                    }
                },

                formatLabel(key) {
                    return key.replace(/_/g, ' ').replace(/([A-Z])/g, ' $1').trim();
                },

                formatValue(value) {
                    if (Array.isArray(value)) {
                        return value.map(v => typeof v === 'object' ? JSON.stringify(v) : v).join(', ');
                    }
                    if (typeof value === 'object' && value !== null) {
                        return JSON.stringify(value);
                    }
                    if (typeof value === 'boolean') {
                        return value ? 'Yes' : 'No';
                    }
                    return String(value);
                },
            },
        });
    </script>
@endPushOnce
