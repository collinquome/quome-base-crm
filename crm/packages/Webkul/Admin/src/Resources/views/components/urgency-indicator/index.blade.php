@props([
    'entityType' => 'person',
    'entityId' => null,
])

<v-urgency-indicator
    entity-type="{{ $entityType }}"
    entity-id="{{ $entityId }}"
    data-testid="urgency-indicator"
></v-urgency-indicator>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-urgency-indicator-template"
    >
        <div v-if="nextAction" class="flex items-center gap-3 rounded-lg border px-4 py-3" :class="urgencyContainerClass" data-testid="urgency-indicator-card">
            <!-- Urgency Dot -->
            <div class="flex h-3 w-3 flex-shrink-0 rounded-full" :class="urgencyDotClass" data-testid="urgency-dot"></div>

            <!-- Content -->
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium" :class="urgencyTextClass">
                        @{{ urgencyLabel }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityBadgeClass">
                        @{{ nextAction.priority }}
                    </span>
                </div>
                <div class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                    <span :class="actionTypeIcon(nextAction.action_type)" class="text-xs"></span>
                    @{{ nextAction.description || nextAction.action_type }}
                    <span v-if="nextAction.due_date" class="ml-1 text-xs">
                        &middot; @{{ formatDate(nextAction.due_date) }}
                    </span>
                </div>
            </div>

            <!-- Quick Actions -->
            <button
                type="button"
                class="flex items-center gap-1 rounded-md border border-green-600 bg-green-600 px-2.5 py-1 text-xs font-semibold text-white transition-all hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600"
                @click="completeAction"
                title="Complete"
            >
                <span class="icon-checkbox-outline text-sm"></span>
                Done
            </button>
        </div>
        <div v-else-if="loaded && !nextAction" class="flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-400 dark:border-gray-700 dark:text-gray-500" data-testid="urgency-indicator-empty">
            <span class="icon-activity text-base"></span>
            No next action set
        </div>
    </script>

    <script type="module">
        app.component('v-urgency-indicator', {
            template: '#v-urgency-indicator-template',

            props: {
                entityType: { type: String, required: true },
                entityId: { type: [String, Number], required: true },
            },

            data() {
                return {
                    nextAction: null,
                    loaded: false,
                    urgency: 'none', // overdue, today, this_week, upcoming, none
                };
            },

            computed: {
                urgencyLabel() {
                    const labels = {
                        overdue: 'Overdue',
                        today: 'Due Today',
                        this_week: 'Due This Week',
                        upcoming: 'Upcoming',
                        none: 'No Due Date',
                    };
                    return labels[this.urgency] || 'Next Action';
                },

                urgencyDotClass() {
                    return {
                        overdue: 'bg-red-500',
                        today: 'bg-orange-500',
                        this_week: 'bg-yellow-500',
                        upcoming: 'bg-green-500',
                        none: 'bg-gray-400',
                    }[this.urgency] || 'bg-gray-400';
                },

                urgencyContainerClass() {
                    return {
                        overdue: 'border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/10',
                        today: 'border-orange-200 bg-orange-50 dark:border-orange-900/50 dark:bg-orange-900/10',
                        this_week: 'border-yellow-200 bg-yellow-50 dark:border-yellow-900/50 dark:bg-yellow-900/10',
                        upcoming: 'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-900/10',
                        none: 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/50',
                    }[this.urgency] || 'border-gray-200 bg-gray-50';
                },

                urgencyTextClass() {
                    return {
                        overdue: 'text-red-700 dark:text-red-400',
                        today: 'text-orange-700 dark:text-orange-400',
                        this_week: 'text-yellow-700 dark:text-yellow-400',
                        upcoming: 'text-green-700 dark:text-green-400',
                        none: 'text-gray-500 dark:text-gray-400',
                    }[this.urgency] || 'text-gray-500';
                },

                priorityBadgeClass() {
                    if (!this.nextAction) return '';
                    return {
                        urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                    }[this.nextAction.priority] || 'bg-gray-100 text-gray-600';
                },
            },

            mounted() {
                this.fetchNextAction();
            },

            methods: {
                async fetchNextAction() {
                    try {
                        const response = await this.$axios.get('/admin/action-stream/list', {
                            params: {
                                actionable_type: this.entityType,
                                actionable_id: this.entityId,
                                status: 'pending',
                                per_page: 1,
                            },
                        });

                        const actions = response.data?.data || [];
                        if (actions.length > 0) {
                            this.nextAction = actions[0];
                            this.urgency = this.calculateUrgency(this.nextAction.due_date);
                        }
                    } catch (error) {
                        console.error('Failed to fetch next action:', error);
                    } finally {
                        this.loaded = true;
                    }
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

                async completeAction() {
                    if (!this.nextAction) return;
                    try {
                        await this.$axios.post(`/admin/action-stream/${this.nextAction.id}/complete`);
                        this.nextAction = null;
                        this.loaded = false;
                        this.fetchNextAction();
                    } catch (error) {
                        console.error('Failed to complete action:', error);
                    }
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
                    return {
                        call: 'icon-call',
                        email: 'icon-mail',
                        meeting: 'icon-activity',
                        task: 'icon-checkbox-outline',
                        custom: 'icon-note',
                    }[type] || 'icon-activity';
                },
            },
        });
    </script>
@endPushOnce
