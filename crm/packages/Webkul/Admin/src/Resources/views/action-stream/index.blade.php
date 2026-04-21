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
                    @click="$refs.actionStreamApp.showCreateModal()"
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
                <div v-else-if="actions.length === 0" class="flex flex-col items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-8 py-32 dark:border-gray-800 dark:bg-gray-900" data-testid="action-stream-empty">
                    <span class="icon-activity text-6xl text-gray-300 dark:text-gray-600"></span>
                    <p class="mt-6 text-lg font-medium text-gray-500 dark:text-gray-400">No pending actions</p>
                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Create a new action to get started</p>
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
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="urgencyLabelClass(action.due_date)"
                                        v-text="urgencyLabel(action.due_date)"
                                        data-testid="action-urgency-badge"
                                    ></span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="priorityBadgeClass(action.priority)"
                                        v-text="action.priority"
                                    ></span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="action.actionable">
                                        <span class="icon-contact text-xs"></span>
                                        @{{ action.actionable?.name || action.actionable?.title || action.actionable_type + ' #' + action.actionable_id }}
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
                        },
                        sortBy: 'due_date',
                        pagination: {
                            currentPage: 1,
                            lastPage: 1,
                            total: 0,
                        },
                    };
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

                    showCreateModal() {
                        // For now, redirect to the API-based create flow
                        // In future iterations, this would open an inline modal
                        window.location.href = '/admin/activities';
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
