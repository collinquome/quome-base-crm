<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.create.title')
    </x-slot>

    {!! view_render_event('admin.leads.create.form.before') !!}

    <!-- Create Lead Form -->
    <x-admin::form :action="route('admin.leads.store')" id="lead-create-form">
        <div class="flex flex-col gap-4">
            <div class="sticky z-20 flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" style="top: 73px;">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="leads.create" />

                    <div class="flex items-center gap-2">
                        <div class="text-xl font-bold dark:text-white">
                            @lang('admin::app.leads.create.title')
                        </div>
                        <span
                            id="lead-draft-indicator"
                            class="hidden rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                            data-testid="lead-draft-indicator"
                        >
                            Draft restored
                        </span>
                    </div>
                </div>

                {!! view_render_event('admin.leads.create.save_button.before') !!}

                <div class="flex items-center gap-x-2.5">
                    <!-- Save button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.leads.create.form_buttons.before') !!}

                        <button
                            type="button"
                            class="secondary-button hidden"
                            id="lead-draft-clear-btn"
                            data-testid="lead-draft-clear-btn"
                        >
                            Discard draft
                        </button>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.leads.create.save-btn')
                        </button>

                        {!! view_render_event('admin.leads.create.form_buttons.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.leads.create.save_button.after') !!}
            </div>

            @if (request('stage_id'))
                <input
                    type="hidden"
                    id="lead_pipeline_stage_id"
                    name="lead_pipeline_stage_id"
                    value="{{ request('stage_id') }}"
                />
            @endif

            @if (request('pipeline_id'))
                <input
                    type="hidden"
                    id="lead_pipeline_id"
                    name="lead_pipeline_id"
                    value="{{ request('pipeline_id') }}"
                />
            @endif

            <!-- Lead Create Component -->
            <v-lead-create>
                <x-admin::shimmer.leads.datagrid />
            </v-lead-create>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.leads.create.form.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-lead-create-template"
        >
            <div class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.leads.edit.form_controls.before') !!}

                <div class="flex w-full gap-2 border-b border-gray-200 dark:border-gray-800">
                    <!-- Tabs -->
                    <template
                        v-for="tab in tabs"
                        :key="tab.id"
                    >
                        {!! view_render_event('admin.leads.create.tabs.before') !!}

                        <a
                            :href="'#' + tab.id"
                            :class="[
                                'inline-block px-3 py-2.5 border-b-2  text-sm font-medium ',
                                activeTab === tab.id
                                ? 'text-brandColor border-brandColor dark:brandColor dark:brandColor'
                                : 'text-gray-600 dark:text-gray-300  border-transparent hover:text-gray-800 hover:border-gray-400 dark:hover:border-gray-400  dark:hover:text-white'
                            ]"
                            @click="scrollToSection(tab.id)"
                            :text="tab.label"
                        >
                        </a>

                        {!! view_render_event('admin.leads.create.tabs.after') !!}
                    </template>
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.leads.create.details.before') !!}

                    <!-- Details section -->
                    <div
                        class="flex flex-col gap-4"
                        id="lead-details"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.details')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.details-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            {!! view_render_event('admin.leads.create.details.attributes.before') !!}

                            <!-- Lead Details Title and Description -->
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    ['code', 'NOTIN', ['lead_value', 'lead_type_id', 'lead_source_id', 'expected_close_date', 'user_id', 'lead_pipeline_id', 'lead_pipeline_stage_id']],
                                    'entity_type' => 'leads',
                                    'quick_add'   => 1
                                ])"
                                :custom-validations="[
                                    'expected_close_date' => [
                                        'date_format:yyyy-MM-dd',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                            />

                            <!-- Lead Details Other input fields -->
                            <div class="flex gap-4 max-sm:flex-wrap">
                                <div class="w-full">
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['lead_value', 'lead_type_id', 'lead_source_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        :custom-validations="[
                                            'expected_close_date' => [
                                                'date_format:yyyy-MM-dd',
                                                'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                            ],
                                        ]"
                                    />
                                </div>

                                <div class="w-full">
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['expected_close_date', 'user_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        :custom-validations="[
                                            'expected_close_date' => [
                                                'date_format:yyyy-MM-dd',
                                                'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                            ],
                                        ]"
                                        :defaults="['user_id' => auth()->guard('user')->id()]"
                                    />
                                </div>
                            </div>

                            {!! view_render_event('admin.leads.create.details.attributes.after') !!}
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.details.after') !!}

                    {!! view_render_event('admin.leads.create.contact_person.before') !!}

                    <!-- Contact Person -->
                    <div
                        class="flex flex-col gap-4"
                        id="contact-person"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.contact-person')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.contact-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            <!-- Contact Person Component -->
                            @include('admin::leads.common.contact')
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.contact_person.after') !!}

                    <!-- Product Section -->
                    <div
                        class="flex flex-col gap-4"
                        id="products"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.products')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.products-info')
                            </p>
                        </div>

                        <div>
                            <!-- Product Component -->
                            @include('admin::leads.common.products')
                        </div>
                    </div>
                </div>

                {!! view_render_event('admin.leads.form_controls.after') !!}
            </div>
        </script>

        <script type="module">
            app.component('v-lead-create', {
                template: '#v-lead-create-template',

                data() {
                    return {
                        activeTab: 'lead-details',

                        tabs: [
                            { id: 'lead-details', label: '@lang('admin::app.leads.create.details')' },
                            { id: 'contact-person', label: '@lang('admin::app.leads.create.contact-person')' },
                            { id: 'products', label: '@lang('admin::app.leads.create.products')' }
                        ],
                    };
                },

                methods: {
                    /**
                     * Scroll to the section.
                     *
                     * @param {String} tabId
                     *
                     * @returns {void}
                     */
                    scrollToSection(tabId) {
                        const section = document.getElementById(tabId);

                        if (section) {
                            section.scrollIntoView({ behavior: 'smooth' });
                        }
                    },
                },
            });
        </script>

        <!-- Lead Draft Persistence — saves WIP form state to localStorage and restores on reload -->
        <script type="module">
            (function leadDraftPersistence() {
                const DRAFT_KEY = 'crm-lead-draft-v1';
                const DEBOUNCE_MS = 400;
                const SKIP_TYPES = new Set(['password', 'file', 'submit', 'button', 'reset']);
                const SKIP_NAMES = new Set(['_token', '_method']);

                const getIndicator = () => document.getElementById('lead-draft-indicator');
                const getClearBtn = () => document.getElementById('lead-draft-clear-btn');

                const getForm = () => {
                    // Prefer the id we set on the create form; fall back to the form
                    // that contains the "title" attribute field, since the admin layout
                    // renders other forms (e.g., logout) ahead of the lead form.
                    const byId = document.getElementById('lead-create-form');
                    if (byId && byId.tagName === 'FORM') return byId;
                    const titleInput = document.querySelector('input[name="title"]');
                    return titleInput?.closest('form') ?? null;
                };

                const canPersist = (el) => {
                    if (! el || ! el.name) return false;
                    if (SKIP_NAMES.has(el.name)) return false;
                    if (el.type && SKIP_TYPES.has(el.type)) return false;
                    return true;
                };

                const snapshot = () => {
                    const form = getForm();
                    if (! form) return {};
                    const values = {};
                    for (const el of form.elements) {
                        if (! canPersist(el)) continue;

                        if (el.type === 'checkbox') {
                            values[el.name] = el.checked ? (el.value || '1') : '';
                        } else if (el.type === 'radio') {
                            if (el.checked) values[el.name] = el.value;
                        } else {
                            values[el.name] = el.value ?? '';
                        }
                    }
                    return values;
                };

                const save = () => {
                    const values = snapshot();
                    if (! Object.keys(values).length) return;
                    try {
                        localStorage.setItem(DRAFT_KEY, JSON.stringify({
                            values,
                            savedAt: Date.now(),
                        }));
                        showClearButton();
                    } catch (_) { /* quota or private mode — ignore */ }
                };

                const clear = () => {
                    try { localStorage.removeItem(DRAFT_KEY); } catch (_) {}
                    const indicator = getIndicator();
                    const clearBtn = getClearBtn();
                    if (indicator) indicator.classList.add('hidden');
                    if (clearBtn) clearBtn.classList.add('hidden');
                };

                const showClearButton = () => {
                    const clearBtn = getClearBtn();
                    if (clearBtn) clearBtn.classList.remove('hidden');
                };

                let restoreDone = false;

                const restore = () => {
                    if (restoreDone) return false;

                    const form = getForm();
                    if (! form) return false;

                    let payload;
                    try {
                        const raw = localStorage.getItem(DRAFT_KEY);
                        if (! raw) { restoreDone = true; return false; }
                        payload = JSON.parse(raw);
                    } catch (_) { restoreDone = true; return false; }
                    if (! payload?.values) { restoreDone = true; return false; }

                    let restoredAny = false;
                    for (const el of form.elements) {
                        if (! canPersist(el)) continue;
                        if (! (el.name in payload.values)) continue;

                        const saved = payload.values[el.name];
                        let changed = false;

                        if (el.type === 'checkbox') {
                            const want = !! saved;
                            if (el.checked !== want) { el.checked = want; changed = true; }
                        } else if (el.type === 'radio') {
                            const want = (el.value === saved);
                            if (el.checked !== want) { el.checked = want; changed = true; }
                        } else if (el.value !== saved) {
                            el.value = saved;
                            changed = true;
                        }

                        if (changed) {
                            // Only fire events when we actually changed something, to avoid
                            // fighting Vue's reactivity on fields that were already correct.
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                            restoredAny = true;
                        }
                    }

                    restoreDone = true;

                    if (restoredAny) {
                        const indicator = getIndicator();
                        if (indicator) indicator.classList.remove('hidden');
                        showClearButton();
                    }
                    return restoredAny;
                };

                // Debounce watcher.
                let timer;
                const scheduleSave = () => {
                    clearTimeout(timer);
                    timer = setTimeout(save, DEBOUNCE_MS);
                };

                // Delegate at document level so we catch fields rendered by Vue after mount.
                const onAnyChange = (e) => {
                    const form = getForm();
                    if (! form || ! e.target || ! form.contains(e.target)) return;
                    if (! canPersist(e.target)) return;
                    scheduleSave();
                };
                document.addEventListener('input', onAnyChange, { passive: true });
                document.addEventListener('change', onAnyChange, { passive: true });

                // Clear on submit (draft survives if validation fails — no navigation).
                document.addEventListener('submit', (e) => {
                    const form = getForm();
                    if (! form || e.target !== form) return;
                    setTimeout(clear, 100);
                });

                document.addEventListener('click', (e) => {
                    const btn = getClearBtn();
                    if (! btn || e.target !== btn) return;
                    if (confirm('Discard the saved draft? This will clear all fields you have not yet submitted.')) {
                        clear();
                        window.location.reload();
                    }
                });

                // Poll for the form and its Vue-rendered inputs, then restore once ready.
                let attempts = 0;
                const attemptRestore = () => {
                    attempts++;
                    const form = getForm();
                    if (form && form.elements.length >= 3) {
                        restore();
                        return;
                    }
                    if (attempts < 40) {
                        setTimeout(attemptRestore, 100);
                    }
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', attemptRestore);
                } else {
                    attemptRestore();
                }
            })();
        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            html {
                scroll-behavior: smooth;
            }
        </style>
    @endPushOnce
</x-admin::layouts>
