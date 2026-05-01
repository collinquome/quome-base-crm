<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.dashboard.index.title')
    </x-slot>

    <!-- Head Details Section -->
    {!! view_render_event('admin.dashboard.index.header.before') !!}

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        {!! view_render_event('admin.dashboard.index.header.left.before') !!}

        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                @lang('admin::app.dashboard.index.title')
            </p>
        </div>

        {!! view_render_event('admin.dashboard.index.header.left.after') !!}

        <!-- Actions -->
        {!! view_render_event('admin.dashboard.index.header.right.before') !!}

        <v-dashboard-filters>
            <!-- Shimmer -->
            <div class="flex gap-1.5">
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
            </div>
        </v-dashboard-filters>

        {!! view_render_event('admin.dashboard.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.header.after') !!}

    <!-- Action Stream (Dashboard) -->
    <v-dashboard-action-stream data-testid="dashboard-action-stream"></v-dashboard-action-stream>

    <!-- Body Component -->
    {!! view_render_event('admin.dashboard.index.content.before') !!}

    <div class="mt-3.5 flex gap-4 max-xl:flex-wrap">
        <!-- Left Section -->
        {!! view_render_event('admin.dashboard.index.content.left.before') !!}

        <div class="flex flex-1 flex-col gap-4 max-xl:flex-auto">
            <!-- Revenue Stats -->
            @include('admin::dashboard.index.revenue')

            <!-- Over All Stats -->
            @include('admin::dashboard.index.over-all')

            <!-- Total Leads Stats -->
            @include('admin::dashboard.index.total-leads')

            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Total Products -->
                @include('admin::dashboard.index.top-selling-products')

                <!-- Total Persons -->
                @include('admin::dashboard.index.top-persons')
            </div>
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}

        <!-- Right Section -->
        {!! view_render_event('admin.dashboard.index.content.right.before') !!}

        <div class="flex w-[378px] max-w-full flex-col gap-4 max-sm:w-full">
            <!-- Revenue by Types -->
            @include('admin::dashboard.index.open-leads-by-states')

            <!-- Revenue by Sources -->
            @include('admin::dashboard.index.revenue-by-sources')

            <!-- Revenue by Types -->
            @include('admin::dashboard.index.revenue-by-types')
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.content.after') !!}

    @pushOnce('scripts')

        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="module"
            src="https://cdn.jsdelivr.net/npm/chartjs-chart-funnel@4.2.1/build/index.umd.min.js"
        >
        </script>

        <script
            type="text/x-template"
            id="v-dashboard-action-stream-template"
        >
            <div class="mb-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" data-testid="dashboard-action-stream-panel">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2.5 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Action Stream</h3>
                        <span
                            v-if="overdueCount > 0"
                            class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                            data-testid="dashboard-overdue-badge"
                        >
                            @{{ overdueCount }} overdue
                        </span>
                    </div>
                    <a
                        href="/admin/action-stream"
                        class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                        data-testid="action-stream-view-all"
                    >
                        View All
                    </a>
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="space-y-2 p-4">
                    <div v-for="n in 3" :key="n" class="flex animate-pulse items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        <div class="flex-1 space-y-1">
                            <div class="h-3.5 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                            <div class="h-3 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                        </div>
                    </div>
                </div>

                <!-- Empty -->
                <div v-else-if="actions.length === 0" class="flex items-center gap-2 px-4 py-4 text-sm text-gray-400 dark:text-gray-500" data-testid="dashboard-action-stream-empty">
                    <span class="icon-activity text-base"></span>
                    No pending actions — you're all caught up!
                </div>

                <!-- Action Items -->
                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800" data-testid="dashboard-action-stream-list">
                    <div
                        v-for="action in actions"
                        :key="action.id"
                        class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        :class="{ 'border-l-3': true, [urgencyBorderClass(action.due_date)]: true }"
                        data-testid="dashboard-action-stream-item"
                    >
                        <!-- Type Icon -->
                        <div
                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
                            :class="actionTypeIconBg(action.action_type)"
                        >
                            <span :class="actionTypeIcon(action.action_type)" class="text-sm text-white"></span>
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                    @{{ action.description || action.action_type }}
                                </span>
                                <span
                                    class="flex-shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                    :class="urgencyLabelClass(action.due_date)"
                                >
                                    @{{ urgencyLabel(action.due_date) }}
                                </span>
                                <span
                                    class="flex-shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                    :class="priorityBadgeClass(action.priority)"
                                >
                                    @{{ action.priority }}
                                </span>
                            </div>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <a
                                    v-if="action.actionable"
                                    :href="entityLink(action)"
                                    class="hover:text-blue-600 hover:underline dark:hover:text-blue-400"
                                    @click.stop
                                >
                                    <span class="icon-contact text-[10px]"></span>
                                    @{{ action.actionable?.name || action.actionable?.title || action.actionable_type + ' #' + action.actionable_id }}
                                </a>
                                <span v-if="action.due_date">
                                    <span class="icon-calendar text-[10px]"></span>
                                    @{{ formatDate(action.due_date) }}
                                </span>
                            </div>
                        </div>

                        <!-- Complete Button -->
                        <button
                            type="button"
                            class="flex-shrink-0 rounded-md border border-green-600 bg-green-600 px-2.5 py-1 text-xs font-semibold text-white transition-all hover:bg-green-700 hover:border-green-700"
                            @click="completeAction(action.id)"
                            data-testid="dashboard-action-complete-btn"
                        >
                            Complete
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-dashboard-filters-template"
        >
            {!! view_render_event('admin.dashboard.index.date_filters.before') !!}

            <div class="flex flex-wrap gap-1.5">
                <!-- User Filter (hidden when the caller can only see their own data) -->
                <select
                    v-if="users.length > 1"
                    v-model="filters.user_id"
                    class="flex min-h-[39px] rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    data-testid="dashboard-user-filter"
                >
                    <!-- "All Team Members" only shown when the caller isn't scoped to a subset -->
                    <option v-if="!scopedToSelf" value="">All Team Members</option>
                    <option
                        v-for="user in users"
                        :key="user.id"
                        :value="user.id"
                    >
                        @{{ user.name }}
                    </option>
                </select>

                <!-- Timeframe Quick Selects -->
                <div class="flex rounded-md border dark:border-gray-800" data-testid="dashboard-timeframe-buttons">
                    <button
                        v-for="tf in timeframes"
                        :key="tf.label"
                        type="button"
                        class="px-3 py-2 text-xs font-medium transition-colors first:rounded-l-md last:rounded-r-md"
                        :class="activeTimeframe === tf.label
                            ? 'bg-brandColor text-white'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        @click="setTimeframe(tf)"
                        :data-testid="'timeframe-' + tf.label.toLowerCase()"
                    >
                        @{{ tf.label }}
                    </button>
                </div>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.start"
                        placeholder="@lang('admin::app.dashboard.index.start-date')"
                    />
                </x-admin::flat-picker.date>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.end"
                        placeholder="@lang('admin::app.dashboard.index.end-date')"
                    />
                </x-admin::flat-picker.date>
            </div>

            {!! view_render_event('admin.dashboard.index.date_filters.after') !!}
        </script>

        <script type="module">
            app.component('v-dashboard-action-stream', {
                template: '#v-dashboard-action-stream-template',

                data() {
                    return {
                        actions: [],
                        isLoading: true,
                        overdueCount: 0,
                        selectedUserId: '',
                    };
                },

                mounted() {
                    this.fetchActions();
                    this.fetchOverdueCount();

                    // Re-fetch whenever the dashboard user-picker / filters change
                    // so the action stream tracks whichever rep the manager is viewing.
                    this.$emitter.on('reporting-filter-updated', this.handleFilterUpdate);
                },

                beforeUnmount() {
                    this.$emitter.off('reporting-filter-updated', this.handleFilterUpdate);
                },

                methods: {
                    handleFilterUpdate(filters) {
                        const next = filters?.user_id || '';
                        if (next === this.selectedUserId) return;
                        this.selectedUserId = next;
                        this.fetchActions();
                        this.fetchOverdueCount();
                    },

                    async fetchActions() {
                        this.isLoading = true;
                        try {
                            const params = { per_page: 10 };
                            if (this.selectedUserId) params.user_id = this.selectedUserId;
                            const response = await this.$axios.get('/admin/action-stream/stream', { params });
                            this.actions = response.data?.data || [];
                        } catch {
                            this.actions = [];
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    async fetchOverdueCount() {
                        try {
                            const params = {};
                            if (this.selectedUserId) params.user_id = this.selectedUserId;
                            const response = await this.$axios.get('/admin/action-stream/overdue-count', { params });
                            this.overdueCount = response.data?.data?.overdue_count || 0;
                        } catch {
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

                    entityLink(action) {
                        if (action.actionable_type === 'leads') {
                            return `/admin/leads/view/${action.actionable_id}`;
                        }
                        if (action.actionable_type === 'persons') {
                            return `/admin/contacts/persons/view/${action.actionable_id}`;
                        }
                        return '#';
                    },

                    // Parse YYYY-MM-DD as a LOCAL date. Bare date strings handed to
                        // `new Date()` are interpreted as UTC midnight, which becomes the
                        // previous calendar day for any user west of UTC (e.g., Pacific) —
                        // that's how an action set for tomorrow ends up labeled "Due Today".
                    parseLocalDate(dateStr) {
                        if (!dateStr) return null;
                        const m = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})/);
                        if (!m) return new Date(dateStr);
                        return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
                    },

                    formatDate(dateStr) {
                        if (!dateStr) return '';
                        const date = this.parseLocalDate(dateStr);
                        const today = new Date(); today.setHours(0,0,0,0);
                        const tomorrow = new Date(today); tomorrow.setDate(tomorrow.getDate() + 1);
                        if (date.toDateString() === today.toDateString()) return 'Today';
                        if (date.toDateString() === tomorrow.toDateString()) return 'Tomorrow';
                        const diff = Math.round((date - today) / (1000 * 60 * 60 * 24));
                        if (diff < 0) return `${Math.abs(diff)}d overdue`;
                        if (diff <= 7) return `In ${diff}d`;
                        return date.toLocaleDateString();
                    },

                    calculateUrgency(dueDate) {
                        if (!dueDate) return 'none';
                        const today = new Date(); today.setHours(0,0,0,0);
                        const due = this.parseLocalDate(dueDate); due.setHours(0,0,0,0);
                        const diffDays = Math.round((due - today) / (1000*60*60*24));
                        if (diffDays < 0) return 'overdue';
                        if (diffDays === 0) return 'today';
                        if (diffDays <= 7) return 'this_week';
                        return 'upcoming';
                    },

                    urgencyBorderClass(dueDate) {
                        return { overdue: '!border-l-red-500', today: '!border-l-orange-500', this_week: '!border-l-yellow-500', upcoming: '!border-l-green-500', none: '!border-l-gray-400' }[this.calculateUrgency(dueDate)] || '!border-l-gray-300';
                    },

                    urgencyLabel(dueDate) {
                        return { overdue: 'Overdue', today: 'Due Today', this_week: 'This Week', upcoming: 'Upcoming', none: 'No Date' }[this.calculateUrgency(dueDate)] || '';
                    },

                    urgencyLabelClass(dueDate) {
                        const u = this.calculateUrgency(dueDate);
                        return { overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', today: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', this_week: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400', upcoming: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', none: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }[u] || 'bg-gray-100 text-gray-500';
                    },

                    priorityBadgeClass(priority) {
                        return { urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400', normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }[priority] || 'bg-gray-100 text-gray-600';
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

        <script type="module">
            app.component('v-dashboard-filters', {
                template: '#v-dashboard-filters-template',

                data() {
                    return {
                        users: [],

                        scopedToSelf: false,

                        activeTimeframe: 'Month',

                        timeframes: [
                            { label: 'Week',     days: 7 },
                            { label: 'Month',    days: 30 },
                            { label: 'Quarter',  days: 90 },
                            { label: 'Year',     days: 365 },
                            { label: 'Lifetime', days: 3650 },
                        ],

                        filters: {
                            channel: '',

                            user_id: '',

                            start: "{{ $startDate->format('Y-m-d') }}",

                            end: "{{ $endDate->format('Y-m-d') }}",
                        }
                    }
                },

                mounted() {
                    this.fetchUsers();
                },

                watch: {
                    filters: {
                        handler() {
                            this.$emitter.emit('reporting-filter-updated', this.filters);
                        },

                        deep: true
                    }
                },

                methods: {
                    async fetchUsers() {
                        try {
                            const response = await this.$axios.get('/admin/team-stream/members');
                            const data = response.data || {};
                            this.users = data.data || [];

                            // If the server signals this user is scoped (group / individual),
                            // drop "All Team Members" and preselect the auth user so the
                            // dashboard always reflects a single, owned scope.
                            this.scopedToSelf = !! data.scoped;
                            if (this.scopedToSelf && data.current_user?.id) {
                                this.filters.user_id = data.current_user.id;
                            }
                        } catch {
                            this.users = [];
                            this.scopedToSelf = false;
                        }
                    },

                    setTimeframe(tf) {
                        this.activeTimeframe = tf.label;
                        const end = new Date();
                        const start = new Date();
                        start.setDate(end.getDate() - tf.days);
                        this.filters.start = start.toISOString().split('T')[0];
                        this.filters.end = end.toISOString().split('T')[0];
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
