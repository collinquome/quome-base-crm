@props([
    'entityType' => 'person',
    'entityId' => null,
])

<v-next-action-widget
    entity-type="{{ $entityType }}"
    entity-id="{{ $entityId }}"
    data-testid="next-action-widget"
></v-next-action-widget>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-next-action-widget-template"
    >
        <div class="flex flex-col gap-3">
            <!-- Current Next Action -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-800" data-testid="next-action-section">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Next Action</h4>
                    <button
                        v-if="!showCreateForm"
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                        @click="showCreateForm = true"
                        data-testid="next-action-new-btn"
                    >
                        + New
                    </button>
                </div>

                <!-- Current Action Card -->
                <div v-if="currentAction && !showCreateForm" class="p-4">
                    <div class="flex items-start gap-3" data-testid="next-action-current">
                        <!-- Urgency Dot -->
                        <div class="mt-1 flex h-3 w-3 flex-shrink-0 rounded-full" :class="urgencyDotClass"></div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    @{{ currentAction.description || currentAction.action_type }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="urgencyLabelClass">
                                    @{{ urgencyLabel }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span :class="actionTypeIcon(currentAction.action_type)"></span>
                                <span v-text="currentAction.action_type"></span>
                                <span v-if="currentAction.due_date">&middot; @{{ formatDate(currentAction.due_date) }}</span>
                                <span class="rounded-full px-1.5 py-0.5" :class="priorityBadgeClass" v-text="currentAction.priority"></span>
                            </div>
                        </div>

                        <!-- Complete Button -->
                        <button
                            type="button"
                            class="flex-shrink-0 rounded-md border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 transition-all hover:bg-green-50 dark:border-green-700 dark:text-green-400 dark:hover:bg-green-900/20"
                            @click="completeAndPrompt"
                            data-testid="next-action-complete-btn"
                        >
                            Complete
                        </button>
                    </div>
                </div>

                <!-- Create / Set Next Action Form -->
                <div v-if="showCreateForm" class="p-4" data-testid="next-action-form">
                    <div class="flex flex-col gap-3">
                        <div class="flex gap-2">
                            <select
                                v-model="newAction.action_type"
                                class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                data-testid="next-action-type-select"
                            >
                                <option value="call">Call</option>
                                <option value="email">Email</option>
                                <option value="meeting">Meeting</option>
                                <option value="task">Task</option>
                                <option value="custom">Custom</option>
                            </select>
                            <select
                                v-model="newAction.priority"
                                class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                data-testid="next-action-priority-select"
                            >
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                            </select>
                            <input
                                type="date"
                                v-model="newAction.due_date"
                                class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                data-testid="next-action-due-date"
                            />
                        </div>
                        <input
                            type="text"
                            v-model="newAction.description"
                            placeholder="Describe the next action..."
                            class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            data-testid="next-action-description"
                        />
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                @click="cancelCreate"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                @click="createAction"
                                :disabled="!newAction.description"
                                data-testid="next-action-save-btn"
                            >
                                Save Action
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!currentAction && !showCreateForm && loaded" class="p-4">
                    <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500" data-testid="next-action-empty">
                        <span class="icon-activity text-base"></span>
                        No next action set
                        <button
                            type="button"
                            class="ml-auto text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                            @click="showCreateForm = true"
                        >
                            Set one now
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action History Timeline -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-800" data-testid="action-history-section">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Action History</h4>
                    <span class="text-xs text-gray-400 dark:text-gray-500">@{{ completedActions.length }} completed</span>
                </div>

                <div v-if="completedActions.length > 0" class="max-h-48 overflow-y-auto p-3" data-testid="action-history-list">
                    <div class="relative ml-3 border-l-2 border-gray-200 pl-4 dark:border-gray-700">
                        <div
                            v-for="action in completedActions"
                            :key="action.id"
                            class="relative mb-3 last:mb-0"
                        >
                            <!-- Timeline Dot -->
                            <div class="absolute -left-[1.375rem] top-1 h-2.5 w-2.5 rounded-full bg-green-500"></div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium">@{{ action.description || action.action_type }}</span>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                <span :class="actionTypeIcon(action.action_type)"></span>
                                @{{ action.action_type }}
                                <span v-if="action.completed_at">&middot; Completed @{{ formatDate(action.completed_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-4 text-center text-sm text-gray-400 dark:text-gray-500" data-testid="action-history-empty">
                    No completed actions yet
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-next-action-widget', {
            template: '#v-next-action-widget-template',

            props: {
                entityType: { type: String, required: true },
                entityId: { type: [String, Number], required: true },
            },

            data() {
                return {
                    currentAction: null,
                    completedActions: [],
                    loaded: false,
                    showCreateForm: false,
                    urgency: 'none',
                    newAction: {
                        action_type: 'call',
                        priority: 'normal',
                        due_date: '',
                        description: '',
                    },
                };
            },

            computed: {
                urgencyLabel() {
                    return { overdue: 'Overdue', today: 'Due Today', this_week: 'This Week', upcoming: 'Upcoming', none: 'No Date' }[this.urgency] || '';
                },

                urgencyDotClass() {
                    return { overdue: 'bg-red-500', today: 'bg-orange-500', this_week: 'bg-yellow-500', upcoming: 'bg-green-500', none: 'bg-gray-400' }[this.urgency] || 'bg-gray-400';
                },

                urgencyLabelClass() {
                    return {
                        overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        today: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        this_week: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                        upcoming: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                        none: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                    }[this.urgency] || 'bg-gray-100 text-gray-500';
                },

                priorityBadgeClass() {
                    if (!this.currentAction) return '';
                    return {
                        urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                    }[this.currentAction.priority] || 'bg-gray-100 text-gray-600';
                },
            },

            mounted() {
                this.fetchActions();
            },

            methods: {
                async fetchActions() {
                    try {
                        // Fetch current pending action
                        const pendingRes = await this.$axios.get('/api/v1/action-stream', {
                            params: {
                                actionable_type: this.entityType,
                                actionable_id: this.entityId,
                                status: 'pending',
                                per_page: 1,
                            },
                        });
                        const pending = pendingRes.data?.data || [];
                        if (pending.length > 0) {
                            this.currentAction = pending[0];
                            this.urgency = this.calculateUrgency(this.currentAction.due_date);
                        } else {
                            this.currentAction = null;
                        }

                        // Fetch completed actions for history
                        const completedRes = await this.$axios.get('/api/v1/action-stream', {
                            params: {
                                actionable_type: this.entityType,
                                actionable_id: this.entityId,
                                status: 'completed',
                                per_page: 10,
                            },
                        });
                        this.completedActions = completedRes.data?.data || [];
                    } catch (error) {
                        console.error('Failed to fetch actions:', error);
                    } finally {
                        this.loaded = true;
                    }
                },

                async completeAndPrompt() {
                    if (!this.currentAction) return;
                    try {
                        await this.$axios.post(`/api/v1/action-stream/${this.currentAction.id}/complete`);
                        this.currentAction = null;
                        this.showCreateForm = true;
                        this.fetchActions();
                    } catch (error) {
                        console.error('Failed to complete action:', error);
                    }
                },

                async createAction() {
                    try {
                        await this.$axios.post('/api/v1/action-stream', {
                            actionable_type: this.entityType,
                            actionable_id: this.entityId,
                            ...this.newAction,
                        });
                        this.resetForm();
                        this.showCreateForm = false;
                        this.fetchActions();
                    } catch (error) {
                        console.error('Failed to create action:', error);
                    }
                },

                cancelCreate() {
                    this.showCreateForm = false;
                    this.resetForm();
                },

                resetForm() {
                    this.newAction = {
                        action_type: 'call',
                        priority: 'normal',
                        due_date: '',
                        description: '',
                    };
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

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const date = new Date(dateStr);
                    const today = new Date();
                    const tomorrow = new Date(today);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    if (date.toDateString() === today.toDateString()) return 'Today';
                    if (date.toDateString() === tomorrow.toDateString()) return 'Tomorrow';
                    const diff = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
                    if (diff < 0) return `${Math.abs(diff)}d ago`;
                    if (diff <= 7) return `In ${diff}d`;
                    return date.toLocaleDateString();
                },

                actionTypeIcon(type) {
                    return { call: 'icon-call', email: 'icon-mail', meeting: 'icon-activity', task: 'icon-checkbox-outline', custom: 'icon-note' }[type] || 'icon-activity';
                },
            },
        });
    </script>
@endPushOnce
