<x-admin::layouts>
    <x-slot:title>
        Action Stream
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    Action Stream
                </div>
                <p class="text-gray-600 dark:text-gray-400">Prioritized next actions across all contacts and leads</p>
            </div>

            <div class="flex items-center gap-x-2.5">
                <button
                    type="button"
                    class="primary-button"
                    @click="$refs.actionStreamApp.openCreate()"
                    data-testid="action-stream-create-btn"
                >
                    New Action
                </button>
            </div>
        </div>

        <v-action-stream ref="actionStreamApp"></v-action-stream>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-action-stream-template"
        >
            <div>
                <!-- Filters Bar -->
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900" data-testid="action-stream-filters">
                    <!-- Type Filter -->
                    <select
                        v-model="filters.action_type"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="action-stream-type-filter"
                    >
                        <option value="">All Types</option>
                        <option value="call">Call</option>
                        <option value="email">Email</option>
                        <option value="meeting">Meeting</option>
                        <option value="task">Task</option>
                        <option value="custom">Custom</option>
                    </select>

                    <!-- Priority Filter -->
                    <select
                        v-model="filters.priority"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="action-stream-priority-filter"
                    >
                        <option value="">All Priorities</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="filters.status"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="action-stream-status-filter"
                    >
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="snoozed">Snoozed</option>
                        <option value="">All Statuses</option>
                    </select>

                    <!-- Sort -->
                    <select
                        v-model="sortBy"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="action-stream-sort"
                    >
                        <option value="due_date">Sort by Due Date</option>
                        <option value="priority">Sort by Priority</option>
                    </select>

                    <!-- Overdue Count Badge -->
                    <div v-if="overdueCount > 0" class="ml-auto flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400" data-testid="action-stream-overdue-badge">
                        <span class="icon-clock text-base"></span>
                        @{{ overdueCount }} overdue
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoading" class="space-y-3">
                    <div v-for="n in 5" :key="n" class="animate-pulse rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="h-3 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="actions.length === 0" class="flex p-4 flex-col items-center justify-center rounded-lg border border-gray-200 bg-white text-center dark:border-gray-800 dark:bg-gray-900" data-testid="action-stream-empty">
                    <span class="icon-activity text-7xl text-gray-300 dark:text-gray-600"></span>
                    <p class="mt-8 text-lg font-medium text-gray-500 dark:text-gray-400">No pending actions</p>
                    <p class="mt-3 max-w-xs text-sm text-gray-400 dark:text-gray-500">Create a new action to get started</p>
                    <button
                        type="button"
                        class="mt-4 rounded-md border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold transition-colors hover:bg-blue-700"
                        @click="openCreate()"
                        data-testid="action-stream-empty-create-btn"
                    >
                        + New Action
                    </button>
                </div>

                <!-- Action Items List -->
                <div v-else class="space-y-2" data-testid="action-stream-list">
                    <div
                        v-for="action in actions"
                        :key="action.id"
                        class="flex items-center gap-4 rounded-lg border border-gray-200 bg-white px-4 py-3 transition-all hover:shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
                        :class="{ 'border-l-4': true, [urgencyBorderClass(action.due_date)]: true }"
                        data-testid="action-stream-item"
                    >
                        <component
                            :is="actionableUrl(action) ? 'a' : 'div'"
                            :href="actionableUrl(action) || undefined"
                            class="flex min-w-0 flex-1 items-center gap-4"
                            :class="actionableUrl(action) ? 'cursor-pointer hover:[&_.action-title]:underline' : ''"
                            data-testid="action-stream-item-link"
                        >
                            <!-- Action Type Icon -->
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full"
                                :class="actionTypeIconBg(action.action_type)"
                            >
                                <span :class="actionTypeIcon(action.action_type)" class="text-lg text-white"></span>
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="action-title font-medium text-gray-900 dark:text-white" v-text="action.description || action.action_type"></span>
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="urgencyLabelClass(action.due_date)"
                                        v-text="urgencyLabel(action.due_date)"
                                        data-testid="action-urgency-badge"
                                    ></span>
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="priorityBadgeClass(action.priority)"
                                        v-text="action.priority"
                                    ></span>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="action.actionable">
                                        <span class="icon-contact text-xs"></span>
                                        @{{ action.actionable?.title || action.actionable?.name || action.actionable_type + ' #' + action.actionable_id }}
                                    </span>
                                    <!-- Show the linked person inline for leads so reps see who to contact at a glance. -->
                                    <span v-if="action.actionable?.person?.name" class="text-gray-600 dark:text-gray-400" data-testid="action-stream-person">
                                        &middot; @{{ action.actionable.person.name }}
                                    </span>
                                    <span v-if="action.due_date">
                                        <span class="icon-calendar text-xs"></span>
                                        @{{ formatDate(action.due_date) }}
                                        <span v-if="action.due_time"> @{{ action.due_time }}</span>
                                    </span>
                                </div>
                            </div>
                        </component>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <template v-if="action.status === 'completed'">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition-colors hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-blue-900/20"
                                    @click="reopenAction(action.id)"
                                    title="Re-open this action"
                                    data-testid="action-stream-reopen-btn"
                                >
                                    Reopen
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 text-green-600 transition-all hover:bg-green-50 dark:hover:bg-green-900/20"
                                    @click="completeAction(action.id)"
                                    title="Mark Complete"
                                >
                                    <span class="icon-checkbox-outline text-xl"></span>
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 text-yellow-600 transition-all hover:bg-yellow-50 dark:hover:bg-yellow-900/20"
                                    @click="snoozeAction(action.id)"
                                    title="Snooze"
                                >
                                    <span class="icon-clock text-xl"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Create Action Modal -->
                <div v-if="createOpen" class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/40" @click.self="closeCreate" data-testid="action-stream-create-modal">
                    <div class="max-h-[90vh] w-3/4 p-4 max-w-lg overflow-auto rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">New Action</h3>
                            <button type="button" class="rounded-md p-1 hover:bg-gray-100 dark:hover:bg-gray-800" @click="closeCreate">
                                <span class="icon-cross-large text-xl"></span>
                            </button>
                        </div>

                        <div class="flex flex-col gap-3">
                            <!-- Entity type radios -->
                            <div class="flex items-center gap-4 text-sm" data-testid="action-stream-entity-type">
                                <label class="flex items-center gap-1.5">
                                    <input type="radio" v-model="createForm.entity_type" value="leads" @change="createForm.entity = null; createForm.entity_search = ''" />
                                    Lead
                                </label>
                                <label class="flex items-center gap-1.5">
                                    <input type="radio" v-model="createForm.entity_type" value="persons" @change="createForm.entity = null; createForm.entity_search = ''" />
                                    Contact
                                </label>
                            </div>

                            <!-- Entity search (lightweight inline) -->
                            <div class="relative">
                                <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    @{{ createForm.entity_type === 'leads' ? 'Lead' : 'Contact' }}
                                </label>
                                <div v-if="createForm.entity" class="flex items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                    <span class="font-medium text-gray-900 dark:text-white" v-text="createForm.entity.name || createForm.entity.title || ('#' + createForm.entity.id)"></span>
                                    <button type="button" class="text-xs text-gray-500 hover:text-red-600" @click="createForm.entity = null">Change</button>
                                </div>
                                <input
                                    v-else
                                    type="text"
                                    v-model="createForm.entity_search"
                                    @input="searchEntities"
                                    :placeholder="createForm.entity_type === 'leads' ? 'Search leads…' : 'Search contacts…'"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    data-testid="action-stream-entity-search"
                                />
                                <ul v-if="!createForm.entity && createForm.entity_search.length > 1 && entityResults.length > 0" class="mt-1 max-h-40 overflow-auto rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" data-testid="action-stream-entity-results">
                                    <li
                                        v-for="item in entityResults"
                                        :key="item.id"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                        @click="selectEntity(item)"
                                    >
                                        @{{ item.title || item.name }} <span class="text-xs text-gray-400">#@{{ item.id }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Action fields -->
                            <div class="flex gap-2">
                                <select v-model="createForm.action_type" class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    <option value="call">Call</option>
                                    <option value="email">Email</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="task">Task</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <select v-model="createForm.priority" :style="{ borderLeftWidth: '4px', borderLeftColor: priorityHexColor(createForm.priority) }" class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                </select>
                                <input type="date" v-model="createForm.due_date" class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" />
                            </div>

                            <textarea
                                v-model="createForm.description"
                                rows="3"
                                placeholder="Describe the action…"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                data-testid="action-stream-create-description"
                            ></textarea>

                            <div v-if="createError" class="rounded-md bg-red-50 p-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400" v-text="createError"></div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800" @click="closeCreate">Cancel</button>
                                <button
                                    type="button"
                                    class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                                    :disabled="!canSubmitCreate || createSaving"
                                    @click="submitCreate"
                                    data-testid="action-stream-create-submit"
                                >
                                    @{{ createSaving ? 'Saving…' : 'Save Action' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="pagination.currentPage <= 1"
                        @click="goToPage(pagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Page @{{ pagination.currentPage }} of @{{ pagination.lastPage }}
                    </span>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="pagination.currentPage >= pagination.lastPage"
                        @click="goToPage(pagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-action-stream', {
                template: '#v-action-stream-template',

                data() {
                    return {
                        actions: [],
                        isLoading: true,
                        overdueCount: 0,
                        filters: {
                            action_type: '',
                            priority: '',
                            status: 'pending',
                        },
                        sortBy: 'due_date',
                        pagination: {
                            currentPage: 1,
                            lastPage: 1,
                            total: 0,
                        },
                        // Create-action modal state.
                        createOpen: false,
                        createSaving: false,
                        createError: null,
                        createForm: {
                            entity_type: 'leads',
                            entity: null,
                            entity_search: '',
                            action_type: 'call',
                            priority: 'normal',
                            due_date: new Date().toISOString().split('T')[0],
                            description: '',
                        },
                        entityResults: [],
                        entitySearchTimer: null,
                    };
                },

                computed: {
                    canSubmitCreate() {
                        return !! this.createForm.entity?.id && !! this.createForm.description.trim();
                    },
                },

                mounted() {
                    this.fetchActions();
                    this.fetchOverdueCount();
                },

                methods: {
                    async fetchActions() {
                        this.isLoading = true;

                        try {
                            const params = new URLSearchParams();
                            if (this.filters.action_type) params.set('action_type', this.filters.action_type);
                            if (this.filters.priority) params.set('priority', this.filters.priority);
                            if (this.filters.status) params.set('status', this.filters.status);
                            params.set('page', this.pagination.currentPage);
                            params.set('per_page', 15);

                            const response = await this.$axios.get(`/admin/action-stream/stream?${params}`);
                            const data = response.data;

                            this.actions = data.data || [];
                            this.pagination = {
                                currentPage: data.current_page || 1,
                                lastPage: data.last_page || 1,
                                total: data.total || 0,
                            };
                        } catch (error) {
                            console.error('Failed to fetch actions:', error);
                            this.actions = [];
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    async reopenAction(id) {
                        try {
                            await this.$axios.put(`/admin/action-stream/${id}`, { status: 'pending' });
                            await this.fetchActions();
                            this.fetchOverdueCount();
                        } catch (error) {
                            console.error('Failed to reopen action:', error);
                        }
                    },

                    openCreate() {
                        this.createOpen = true;
                        this.createError = null;
                        this.createForm = {
                            entity_type: 'leads',
                            entity: null,
                            entity_search: '',
                            action_type: 'call',
                            priority: 'normal',
                            due_date: new Date().toISOString().split('T')[0],
                            description: '',
                        };
                        this.entityResults = [];
                    },

                    closeCreate() {
                        this.createOpen = false;
                        this.createError = null;
                    },

                    searchEntities() {
                        if (this.entitySearchTimer) clearTimeout(this.entitySearchTimer);
                        this.entitySearchTimer = setTimeout(async () => {
                            const term = this.createForm.entity_search.trim();
                            if (term.length < 2) {
                                this.entityResults = [];
                                return;
                            }
                            try {
                                const url = this.createForm.entity_type === 'leads'
                                    ? '/admin/leads/search'
                                    : '/admin/contacts/persons/search';
                                const response = await this.$axios.get(url, { params: { query: term } });
                                this.entityResults = response.data?.data || [];
                            } catch (error) {
                                console.error('Entity search failed:', error);
                                this.entityResults = [];
                            }
                        }, 300);
                    },

                    selectEntity(item) {
                        this.createForm.entity = item;
                        this.createForm.entity_search = '';
                        this.entityResults = [];
                    },

                    async submitCreate() {
                        if (! this.canSubmitCreate || this.createSaving) return;
                        this.createSaving = true;
                        this.createError = null;
                        try {
                            const payload = {
                                actionable_type: this.createForm.entity_type,
                                actionable_id: this.createForm.entity.id,
                                action_type: this.createForm.action_type,
                                priority: this.createForm.priority,
                                description: this.createForm.description,
                            };
                            if (this.createForm.due_date) {
                                payload.due_date = this.createForm.due_date;
                            }
                            await this.$axios.post('/admin/action-stream', payload);
                            this.closeCreate();
                            await this.fetchActions();
                            this.fetchOverdueCount();
                        } catch (error) {
                            this.createError = error.response?.data?.message || 'Failed to create action.';
                        } finally {
                            this.createSaving = false;
                        }
                    },

                    priorityHexColor(priority) {
                        return {
                            urgent: '#ef4444',
                            high: '#f97316',
                            normal: '#3b82f6',
                            low: '#9ca3af',
                        }[priority] || '#9ca3af';
                    },

                    async fetchOverdueCount() {
                        try {
                            const response = await this.$axios.get('/admin/action-stream/overdue-count');
                            this.overdueCount = response.data?.data?.overdue_count || 0;
                        } catch (error) {
                            this.overdueCount = 0;
                        }
                    },

                    async completeAction(id) {
                        try {
                            await this.$axios.post(`/admin/action-stream/${id}/complete`);
                            this.actions = this.actions.filter(a => a.id !== id);
                            this.fetchOverdueCount();
                        } catch (error) {
                            console.error('Failed to complete action:', error);
                        }
                    },

                    async snoozeAction(id) {
                        try {
                            await this.$axios.post(`/admin/action-stream/${id}/snooze`, {
                                snooze_until: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
                            });
                            this.fetchActions();
                            this.fetchOverdueCount();
                        } catch (error) {
                            console.error('Failed to snooze action:', error);
                        }
                    },

                    goToPage(page) {
                        this.pagination.currentPage = page;
                        this.fetchActions();
                    },

                    formatDate(dateStr) {
                        if (!dateStr) return '';
                        const date = new Date(dateStr);
                        const today = new Date();
                        const tomorrow = new Date(today);
                        tomorrow.setDate(tomorrow.getDate() + 1);

                        if (date.toDateString() === today.toDateString()) return 'Today';
                        if (date.toDateString() === tomorrow.toDateString()) return 'Tomorrow';

                        const diff = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
                        if (diff < 0) return `${Math.abs(diff)}d overdue`;
                        if (diff <= 7) return `In ${diff}d`;

                        return date.toLocaleDateString();
                    },

                    urgencyBorderClass(dueDate) {
                        const urgency = this.calculateUrgency(dueDate);
                        return {
                            overdue: '!border-l-red-500',
                            today: '!border-l-orange-500',
                            this_week: '!border-l-yellow-500',
                            upcoming: '!border-l-green-500',
                            none: '!border-l-gray-400',
                        }[urgency] || '!border-l-gray-300';
                    },

                    calculateUrgency(dueDate) {
                        if (!dueDate) return 'none';
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const due = new Date(dueDate);
                        due.setHours(0, 0, 0, 0);
                        const diffDays = Math.floor((due - today) / (1000 * 60 * 60 * 24));
                        if (diffDays < 0) return 'overdue';
                        if (diffDays === 0) return 'today';
                        if (diffDays <= 7) return 'this_week';
                        return 'upcoming';
                    },

                    urgencyLabel(dueDate) {
                        const labels = {
                            overdue: 'Overdue',
                            today: 'Due Today',
                            this_week: 'This Week',
                            upcoming: 'Upcoming',
                            none: 'No Date',
                        };
                        return labels[this.calculateUrgency(dueDate)] || '';
                    },

                    urgencyLabelClass(dueDate) {
                        const urgency = this.calculateUrgency(dueDate);
                        return {
                            overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            today: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                            this_week: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                            upcoming: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                            none: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                        }[urgency] || 'bg-gray-100 text-gray-500';
                    },

                    priorityBorderClass(priority) {
                        return {
                            urgent: '!border-l-red-500',
                            high: '!border-l-orange-500',
                            normal: '!border-l-blue-500',
                            low: '!border-l-gray-400',
                        }[priority] || '!border-l-gray-300';
                    },

                    priorityBadgeClass(priority) {
                        return {
                            urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                            normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                        }[priority] || 'bg-gray-100 text-gray-600';
                    },

                    actionableUrl(action) {
                        if (! action.actionable_id) return null;

                        if (action.actionable_type === 'leads' || action.actionable_type === 'lead') {
                            return `/admin/leads/view/${action.actionable_id}`;
                        }

                        if (action.actionable_type === 'persons' || action.actionable_type === 'person') {
                            return `/admin/contacts/persons/view/${action.actionable_id}`;
                        }

                        return null;
                    },

                    actionTypeIcon(type) {
                        return {
                            call: 'icon-call',
                            email: 'icon-mail',
                            meeting: 'icon-activity',
                            task: 'icon-checkbox-outline',
                            custom: 'icon-note',
                        }[type] || 'icon-activity';
                    },

                    actionTypeIconBg(type) {
                        return {
                            call: 'bg-cyan-500',
                            email: 'bg-green-500',
                            meeting: 'bg-blue-500',
                            task: 'bg-purple-500',
                            custom: 'bg-orange-500',
                        }[type] || 'bg-gray-500';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
