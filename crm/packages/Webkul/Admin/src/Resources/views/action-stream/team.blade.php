<x-admin::layouts>
    <x-slot:title>
        Team Stream
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    Team Stream
                </div>
                <p class="text-gray-600 dark:text-gray-400">All team members' actions in a unified feed</p>
            </div>
        </div>

        <v-team-stream></v-team-stream>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-team-stream-template"
        >
            <div>
                <!-- Filters Bar -->
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900" data-testid="team-stream-filters">
                    <!-- User Filter -->
                    <select
                        v-model="filters.user_id"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="team-stream-user-filter"
                    >
                        <option value="">All Members</option>
                        <option v-for="member in members" :key="member.id" :value="member.id">
                            @{{ member.name }}
                        </option>
                    </select>

                    <!-- Type Filter -->
                    <select
                        v-model="filters.action_type"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="team-stream-type-filter"
                    >
                        <option value="">All Types</option>
                        <option value="call">Call</option>
                        <option value="email">Email</option>
                        <option value="meeting">Meeting</option>
                        <option value="task">Task</option>
                        <option value="custom">Custom</option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="filters.status"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        data-testid="team-stream-status-filter"
                    >
                        <option value="">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="snoozed">Snoozed</option>
                    </select>

                    <!-- Date Range -->
                    <input
                        type="date"
                        v-model="filters.due_from"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        placeholder="From"
                        data-testid="team-stream-date-from"
                    />
                    <input
                        type="date"
                        v-model="filters.due_to"
                        @change="fetchActions"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        placeholder="To"
                        data-testid="team-stream-date-to"
                    />
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
                <div v-else-if="actions.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-gray-200 bg-white py-16 dark:border-gray-800 dark:bg-gray-900" data-testid="team-stream-empty">
                    <span class="icon-activity text-6xl text-gray-300 dark:text-gray-600"></span>
                    <p class="mt-4 text-lg font-medium text-gray-500 dark:text-gray-400">No team actions found</p>
                    <p class="text-sm text-gray-400 dark:text-gray-500">Adjust filters or create actions for team members</p>
                </div>

                <!-- Action Items List -->
                <div v-else class="space-y-2" data-testid="team-stream-list">
                    <div
                        v-for="action in actions"
                        :key="action.id"
                        class="flex items-center gap-4 rounded-lg border border-l-4 border-gray-200 bg-white px-4 py-3 transition-all hover:shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
                        :class="urgencyBorderClass(action.due_date)"
                        data-testid="team-stream-item"
                    >
                        <!-- User Avatar -->
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            @{{ (action.user?.name || 'U').charAt(0).toUpperCase() }}
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-white" v-text="action.description || action.action_type"></span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="urgencyLabelClass(action.due_date)"
                                    v-text="urgencyLabel(action.due_date)"
                                    data-testid="team-stream-urgency-badge"
                                ></span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="priorityBadgeClass(action.priority)"
                                    v-text="action.priority"
                                ></span>
                            </div>
                            <div class="mt-0.5 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                <span class="font-medium" data-testid="team-stream-member-name">
                                    @{{ action.user?.name || 'Unknown' }}
                                </span>
                                <span v-if="action.actionable">
                                    <span class="icon-contact text-xs"></span>
                                    @{{ action.actionable?.name || action.actionable?.title || '' }}
                                </span>
                                <span v-if="action.due_date">
                                    <span class="icon-calendar text-xs"></span>
                                    @{{ formatDate(action.due_date) }}
                                </span>
                                <span v-if="action.status === 'completed'" class="text-green-600 dark:text-green-400">
                                    Completed
                                </span>
                            </div>
                        </div>

                        <!-- Action Type Icon -->
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full" :class="actionTypeIconBg(action.action_type)">
                            <span :class="actionTypeIcon(action.action_type)" class="text-sm text-white"></span>
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
            app.component('v-team-stream', {
                template: '#v-team-stream-template',

                data() {
                    return {
                        actions: [],
                        members: [],
                        isLoading: true,
                        filters: {
                            user_id: '',
                            action_type: '',
                            status: '',
                            due_from: '',
                            due_to: '',
                        },
                        pagination: {
                            currentPage: 1,
                            lastPage: 1,
                            total: 0,
                        },
                    };
                },

                mounted() {
                    this.fetchMembers();
                    this.fetchActions();
                },

                methods: {
                    async fetchMembers() {
                        try {
                            const response = await this.$axios.get('/api/v1/team-stream/members');
                            this.members = response.data?.data || [];
                        } catch (error) {
                            console.error('Failed to fetch team members:', error);
                        }
                    },

                    async fetchActions() {
                        this.isLoading = true;

                        try {
                            const params = new URLSearchParams();
                            if (this.filters.user_id) params.set('user_id', this.filters.user_id);
                            if (this.filters.action_type) params.set('action_type', this.filters.action_type);
                            if (this.filters.status) params.set('status', this.filters.status);
                            if (this.filters.due_from) params.set('due_from', this.filters.due_from);
                            if (this.filters.due_to) params.set('due_to', this.filters.due_to);
                            params.set('page', this.pagination.currentPage);
                            params.set('per_page', 20);

                            const response = await this.$axios.get(`/api/v1/team-stream?${params}`);
                            const data = response.data;

                            this.actions = data.data || [];
                            this.pagination = {
                                currentPage: data.current_page || 1,
                                lastPage: data.last_page || 1,
                                total: data.total || 0,
                            };
                        } catch (error) {
                            console.error('Failed to fetch team actions:', error);
                            this.actions = [];
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    goToPage(page) {
                        this.pagination.currentPage = page;
                        this.fetchActions();
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

                    urgencyLabel(dueDate) {
                        return {
                            overdue: 'Overdue',
                            today: 'Due Today',
                            this_week: 'This Week',
                            upcoming: 'Upcoming',
                            none: 'No Date',
                        }[this.calculateUrgency(dueDate)] || '';
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

                    priorityBadgeClass(priority) {
                        return {
                            urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                            high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                            normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                            low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                        }[priority] || 'bg-gray-100 text-gray-600';
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

                    actionTypeIcon(type) {
                        return { call: 'icon-call', email: 'icon-mail', meeting: 'icon-activity', task: 'icon-checkbox-outline', custom: 'icon-note' }[type] || 'icon-activity';
                    },

                    actionTypeIconBg(type) {
                        return { call: 'bg-cyan-500', email: 'bg-green-500', meeting: 'bg-blue-500', task: 'bg-purple-500', custom: 'bg-orange-500' }[type] || 'bg-gray-500';
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
