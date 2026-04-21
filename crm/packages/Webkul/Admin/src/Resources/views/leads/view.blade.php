<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.view.title', ['title' => strip_tags($lead->title)])
    </x-slot>

    <!-- Content -->
    <div class="relative flex gap-4 max-lg:flex-wrap">

        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Lead Header -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumb's -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="leads.view"
                        :entity="$lead"
                    />
                </div>

                {!! view_render_event('admin.leads.view.title.before', ['lead' => $lead]) !!}

                <!-- Title -->
                <h1 class="text-lg font-bold dark:text-white">
                    {{ $lead->title }}
                </h1>

                {!! view_render_event('admin.leads.view.title.after', ['lead' => $lead]) !!}

                <div class="mb-1">
                    @if (($days = $lead->rotten_days) > 0)
                        @php
                            $lead->tags->prepend([
                                'name'  => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                'color' => '#FEE2E2'
                            ]);
                        @endphp
                    @endif

                    {!! view_render_event('admin.leads.view.tags.before', ['lead' => $lead]) !!}

                    <!-- Tags -->
                    <x-admin::tags
                        :attach-endpoint="route('admin.leads.tags.attach', $lead->id)"
                        :detach-endpoint="route('admin.leads.tags.detach', $lead->id)"
                        :added-tags="$lead->tags"
                    />

                    {!! view_render_event('admin.leads.view.tags.after', ['lead' => $lead]) !!}
                </div>
            </div>

            <!-- Contact Person (at the top) -->
            @include ('admin::leads.view.person')

            <!-- Lead Attributes -->
            @include ('admin::leads.view.attributes')

            <!-- Notes / Description (editable from left side) -->
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <v-lead-notes
                    :lead-id="{{ $lead->id }}"
                    initial-notes="{{ e($lead->notes ?? $lead->description ?? '') }}"
                ></v-lead-notes>
            </div>

            <!-- Next Action Widget -->
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <x-admin::next-action-widget
                    entity-type="lead"
                    :entity-id="$lead->id"
                />
            </div>

            <!-- Activity Actions -->
            <div class="flex flex-wrap gap-2 p-4">
                {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                @if (bouncer()->hasPermission('mail.compose'))
                    <x-admin::activities.actions.mail
                        :entity="$lead"
                        entity-control-name="lead_id"
                    />
                @endif

                @if (bouncer()->hasPermission('activities.create'))
                    <x-admin::activities.actions.file
                        :entity="$lead"
                        entity-control-name="lead_id"
                    />

                    <x-admin::activities.actions.activity
                        :entity="$lead"
                        entity-control-name="lead_id"
                    />
                @endif

                {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
            </div>
        </div>

        {!! view_render_event('admin.leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.leads.view.right.before', ['lead' => $lead]) !!}

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Stages Navigation -->
            @include ('admin::leads.view.stages')

            <!-- Activities -->
            {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

            <x-admin::activities
                :endpoint="route('admin.leads.activities.index', $lead->id)"
                :email-detach-endpoint="route('admin.leads.emails.detach', $lead->id)"
                :activeType="request()->query('from') === 'quotes' ? 'quotes' : 'overview'"
                :types="[
                    ['name' => 'file', 'label' => trans('admin::app.components.activities.index.files')],
                    ['name' => 'email', 'label' => trans('admin::app.components.activities.index.emails')],
                    ['name' => 'system', 'label' => trans('admin::app.components.activities.index.change-log')],
                ]"
                :extra-types="[
                    ['name' => 'overview', 'label' => 'Overview'],
                    ['name' => 'products', 'label' => trans('admin::app.leads.view.tabs.products')],
                    ['name' => 'quotes', 'label' => trans('admin::app.leads.view.tabs.quotes')],
                ]"
            >
                <!-- Overview -->
                <x-slot:overview>
                    <div class="flex flex-col gap-6 p-4">
                        <!-- Notes Scratchpad -->
                        <v-lead-notes
                            :lead-id="{{ $lead->id }}"
                            initial-notes="{{ e($lead->notes ?? $lead->description ?? '') }}"
                        ></v-lead-notes>

                        <!-- Next Action -->
                        <x-admin::next-action-widget
                            entity-type="lead"
                            :entity-id="$lead->id"
                        />
                    </div>
                </x-slot>

                <!-- Products -->
                <x-slot:products>
                    @include ('admin::leads.view.products')
                </x-slot>

                <!-- Quotes -->
                <x-slot:quotes>
                    @include ('admin::leads.view.quotes')
                </x-slot>
            </x-admin::activities>

            {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-lead-notes-template">
            <div class="rounded-lg border border-gray-200 dark:border-gray-800" data-testid="lead-notes-section">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Notes</h4>
                    <div class="flex items-center gap-2">
                        <span v-if="saveStatus === 'saving'" class="text-xs text-gray-400">Saving...</span>
                        <span v-else-if="saveStatus === 'saved'" class="text-xs text-green-500">Saved</span>
                        <button
                            v-if="!editing"
                            type="button"
                            class="rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                            @click="startEditing"
                            data-testid="notes-edit-btn"
                        >
                            <span class="icon-edit text-sm"></span> Edit
                        </button>
                        <button
                            v-if="editing"
                            type="button"
                            class="rounded-md border border-blue-600 bg-blue-600 px-2 py-1 text-xs font-medium hover:bg-blue-700"
                            @click="saveNotes"
                            data-testid="notes-save-btn"
                        >
                            Save
                        </button>
                        <button
                            v-if="editing"
                            type="button"
                            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400"
                            @click="cancelEditing"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <div class="p-4">
                    <textarea
                        v-if="editing"
                        v-model="notes"
                        ref="notesTextarea"
                        class="w-full min-h-[100px] rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        placeholder="Add notes about this lead..."
                        data-testid="notes-textarea"
                        @keydown.meta.enter="saveNotes"
                        @keydown.ctrl.enter="saveNotes"
                    ></textarea>
                    <div
                        v-else
                        class="min-h-[40px] cursor-pointer text-sm text-gray-600 dark:text-gray-400"
                        :class="{'text-gray-400 italic': !notes}"
                        @click="startEditing"
                        data-testid="notes-display"
                    >
                        <pre v-if="notes" class="whitespace-pre-wrap font-sans" v-text="notes"></pre>
                        <span v-else>Click to add notes...</span>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-lead-notes', {
                template: '#v-lead-notes-template',

                props: {
                    leadId: { type: [String, Number], required: true },
                    initialNotes: { type: String, default: '' },
                },

                data() {
                    return {
                        notes: this.initialNotes,
                        originalNotes: this.initialNotes,
                        editing: false,
                        saveStatus: '',
                    };
                },

                methods: {
                    startEditing() {
                        this.editing = true;
                        this.$nextTick(() => {
                            this.$refs.notesTextarea?.focus();
                        });
                    },

                    cancelEditing() {
                        this.notes = this.originalNotes;
                        this.editing = false;
                    },

                    async saveNotes() {
                        this.saveStatus = 'saving';
                        try {
                            await this.$axios.put(`/admin/leads/notes/${this.leadId}`, {
                                notes: this.notes,
                            });
                            this.originalNotes = this.notes;
                            this.editing = false;
                            this.saveStatus = 'saved';
                            setTimeout(() => { this.saveStatus = ''; }, 2000);
                        } catch (error) {
                            this.saveStatus = '';
                            const msg = error.response?.data?.message || 'Failed to save notes.';
                            if (typeof this.$emitter !== 'undefined') {
                                this.$emitter.emit('add-flash', { type: 'error', message: msg });
                            }
                        }
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
