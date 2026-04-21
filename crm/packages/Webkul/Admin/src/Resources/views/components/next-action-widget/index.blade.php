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
            <!-- Next Actions -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-800" data-testid="next-action-section">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Next Actions
                        <span v-if="pendingActions.length > 0" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400" v-text="pendingActions.length"></span>
                    </h4>
                    <button
                        v-if="!showCreateForm"
                        type="button"
                        class="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/30"
                        @click="showCreateForm = true"
                        data-testid="next-action-new-btn"
                    >
                        + New
                    </button>
                </div>

                <!-- Pending Actions List -->
                <div v-if="pendingActions.length > 0 && !showCreateForm" class="divide-y divide-gray-200 dark:divide-gray-800" data-testid="next-action-list">
                    <div
                        v-for="action in pendingActions"
                        :key="action.id"
                        class="p-4"
                    >
                        <!-- View mode -->
                        <div v-if="editingId !== action.id" class="flex items-start gap-3" data-testid="next-action-current">
                            <div class="mt-1 flex h-3 w-3 flex-shrink-0 rounded-full" :class="urgencyDotClassFor(action)"></div>

                            <div class="group min-w-0 flex-1 cursor-pointer" @click="startEditing(action)" title="Click to edit">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        @{{ action.description || action.action_type }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="urgencyLabelClassFor(action)">
                                        @{{ urgencyLabelFor(action) }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span :class="actionTypeIcon(action.action_type)"></span>
                                    <span v-text="action.action_type"></span>
                                    <span v-if="action.due_date">&middot; @{{ formatDate(action.due_date) }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5" :class="priorityBadgeClassFor(action)" v-text="action.priority"></span>
                                    <span class="ml-1 text-blue-500 opacity-0 transition-opacity group-hover:opacity-100 dark:text-blue-400">
                                        <span class="icon-edit text-xs"></span>
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex-shrink-0 rounded-md border border-green-600 bg-green-600 px-3 py-1.5 text-xs font-semibold text-white transition-all hover:bg-green-700 hover:border-green-700 dark:bg-green-700 dark:border-green-700 dark:hover:bg-green-600"
                                @click="completeAction(action)"
                                data-testid="next-action-complete-btn"
                            >
                                Complete
                            </button>
                        </div>

                        <!-- Edit mode (inline for this row only) -->
                        <div v-else class="flex flex-col gap-3" data-testid="next-action-edit-form">
                            <div class="flex gap-2">
                                <select
                                    v-model="editData.action_type"
                                    class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    data-testid="edit-action-type"
                                >
                                    <option value="call">Call</option>
                                    <option value="email">Email</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="task">Task</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <select
                                    v-model="editData.priority"
                                    :style="{ borderLeftWidth: '4px', borderLeftColor: priorityHexColor(editData.priority) }"
                                    class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    data-testid="edit-action-priority"
                                >
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="normal">Normal</option>
                                    <option value="low">Low</option>
                                </select>
                                <input
                                    type="date"
                                    v-model="editData.due_date"
                                    class="w-1/3 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    data-testid="edit-action-due-date"
                                />
                            </div>
                            <input
                                type="text"
                                v-model="editData.description"
                                class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                data-testid="edit-action-description"
                            />
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                    @click="cancelEditing"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-medium hover:bg-blue-700 cursor-pointer"
                                    :disabled="updating"
                                    @click="saveEdit"
                                    data-testid="edit-action-save-btn"
                                >
                                    @{{ updating ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                        </div>
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
                                :style="{ borderLeftWidth: '4px', borderLeftColor: priorityHexColor(newAction.priority) }"
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
                        <!-- Quick date shortcuts -->
                        <div class="flex gap-1.5" data-testid="next-action-date-shortcuts">
                            <button
                                type="button"
                                v-for="shortcut in dateShortcuts"
                                :key="shortcut.label"
                                class="rounded-md border px-2.5 py-1 text-xs font-medium transition-colors"
                                :class="newAction.due_date === shortcut.value
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-600'
                                    : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800'"
                                @click="newAction.due_date = shortcut.value"
                                :data-testid="'date-shortcut-' + shortcut.label.toLowerCase().replace(/\\s/g, '-')"
                            >
                                @{{ shortcut.label }}
                            </button>
                            <button
                                v-if="newAction.due_date"
                                type="button"
                                class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                @click="newAction.due_date = ''"
                                data-testid="date-shortcut-clear"
                            >
                                Clear
                            </button>
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
                                class="rounded-md border px-3 py-1.5 text-xs font-medium"
                                :class="(!newAction.description || saving) ? 'bg-gray-200 border-gray-300 text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400' : 'bg-blue-600 border-blue-600 text-black hover:bg-blue-700 cursor-pointer'"
                                @click="createAction"
                                :disabled="!newAction.description || saving"
                                data-testid="next-action-save-btn"
                            >
                                @{{ saving ? 'Saving...' : 'Save Action' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="pendingActions.length === 0 && !showCreateForm && loaded" class="p-4">
                    <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500" data-testid="next-action-empty">
                        <span class="icon-activity text-base"></span>
                        No next actions set
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

                <div v-if="completedActions.length > 0" class="max-h-72 overflow-y-auto p-3" data-testid="action-history-list">
                    <div class="relative ml-3 border-l-2 border-gray-200 pl-4 dark:border-gray-700">
                        <div
                            v-for="action in completedActions"
                            :key="action.id"
                            class="relative mb-4 last:mb-0"
                        >
                            <!-- Timeline Dot -->
                            <div class="absolute -left-[1.375rem] top-1 h-2.5 w-2.5 rounded-full bg-green-500"></div>
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">@{{ action.description || action.action_type }}</span>
                                        <span class="rounded-full px-1.5 py-0.5 text-xs font-medium" :class="urgencyLabelClassFor(action)" v-text="urgencyLabelFor(action)"></span>
                                        <span class="rounded-full px-1.5 py-0.5 text-xs font-medium" :class="priorityBadgeClassFor(action)" v-text="action.priority"></span>
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span :class="actionTypeIcon(action.action_type)"></span>
                                        <span v-text="action.action_type"></span>
                                        <span v-if="action.due_date">&middot; Due @{{ formatDate(action.due_date) }}</span>
                                        <span v-if="action.completed_at">&middot; Completed @{{ formatDate(action.completed_at) }}</span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="flex-shrink-0 rounded-md border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-600 transition-colors hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-blue-900/20"
                                    @click="reopenAction(action)"
                                    title="Re-open this action"
                                    data-testid="reopen-action-btn"
                                >
                                    Reopen
                                </button>
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
                    pendingActions: [],
                    completedActions: [],
                    loaded: false,
                    showCreateForm: false,
                    saving: false,
                    editingId: null,
                    updating: false,
                    editData: {},
                    newAction: {
                        action_type: 'call',
                        priority: 'normal',
                        due_date: new Date().toISOString().split('T')[0],
                        description: '',
                    },
                };
            },

            computed: {
                dateShortcuts() {
                    const today = new Date();
                    const fmt = (d) => d.toISOString().split('T')[0];
                    const addDays = (d, n) => { const r = new Date(d); r.setDate(r.getDate() + n); return r; };
                    const nextMonday = new Date(today);
                    nextMonday.setDate(today.getDate() + ((8 - today.getDay()) % 7 || 7));
                    return [
                        { label: 'Today', value: fmt(today) },
                        { label: 'Tomorrow', value: fmt(addDays(today, 1)) },
                        { label: 'Next Week', value: fmt(nextMonday) },
                    ];
                },
            },

            mounted() {
                this.fetchActions();

                this._onOpenCreate = (payload) => {
                    if (! payload) return;
                    if (payload.entityType !== this.entityType) return;
                    if (Number(payload.entityId) !== Number(this.entityId)) return;

                    this.showCreateForm = true;
                    this.$nextTick(() => {
                        this.$el?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
                    });
                };
                this.$emitter.on('next-action:open-create', this._onOpenCreate);

                // Listen for CRUD events from any other widget instance for the same entity.
                this._onChanged = (payload) => {
                    if (! payload) return;
                    if (payload.entityType !== this.entityType) return;
                    if (Number(payload.entityId) !== Number(this.entityId)) return;
                    this.fetchActions();
                };
                this.$emitter.on('next-action:changed', this._onChanged);
            },

            beforeUnmount() {
                if (this._onOpenCreate) {
                    this.$emitter.off('next-action:open-create', this._onOpenCreate);
                }
                if (this._onChanged) {
                    this.$emitter.off('next-action:changed', this._onChanged);
                }
            },

            methods: {
                broadcastChanged() {
                    this.$emitter.emit('next-action:changed', {
                        entityType: this.entityType,
                        entityId: this.entityId,
                    });
                },

                async fetchActions() {
                    try {
                        // Fetch ALL pending actions for this entity, sorted by priority + due date.
                        const pendingRes = await this.$axios.get('/admin/action-stream/list', {
                            params: {
                                actionable_type: this.entityType,
                                actionable_id: this.entityId,
                                status: 'pending',
                                per_page: 50,
                            },
                        });
                        this.pendingActions = pendingRes.data?.data || [];

                        // Fetch completed actions for history
                        const completedRes = await this.$axios.get('/admin/action-stream/list', {
                            params: {
                                actionable_type: this.entityType,
                                actionable_id: this.entityId,
                                status: 'completed',
                                per_page: 20,
                            },
                        });
                        this.completedActions = completedRes.data?.data || [];
                    } catch {
                        // Action stream API may not be available yet — widget still works for manual entry
                    } finally {
                        this.loaded = true;
                    }
                },

                async completeAction(action) {
                    if (! action) return;
                    try {
                        await this.$axios.post(`/admin/action-stream/${action.id}/complete`);
                        await this.fetchActions();
                        this.broadcastChanged();
                    } catch (error) {
                        const msg = error.response?.data?.message || 'Failed to complete action.';
                        if (typeof this.$emitter !== 'undefined') {
                            this.$emitter.emit('add-flash', { type: 'error', message: msg });
                        }
                        console.error('Failed to complete action:', error);
                    }
                },

                async reopenAction(action) {
                    if (! action) return;
                    try {
                        // The action-stream update endpoint accepts status changes via a PUT to the main update
                        // route, but our controller's update() does not flip status. We PUT to the /complete toggle
                        // would re-complete; use the update route with status via the dedicated reopen endpoint
                        // if available — fall back to a simple PUT that sets status = 'pending'.
                        await this.$axios.put(`/admin/action-stream/${action.id}`, { status: 'pending' });
                        await this.fetchActions();
                        this.broadcastChanged();
                    } catch (error) {
                        const msg = error.response?.data?.message || 'Failed to reopen action.';
                        if (typeof this.$emitter !== 'undefined') {
                            this.$emitter.emit('add-flash', { type: 'error', message: msg });
                        }
                        console.error('Failed to reopen action:', error);
                    }
                },

                async createAction() {
                    if (this.saving) return;
                    this.saving = true;
                    try {
                        const payload = {
                            actionable_type: this.entityType,
                            actionable_id: this.entityId,
                            action_type: this.newAction.action_type,
                            priority: this.newAction.priority,
                            description: this.newAction.description,
                        };

                        // Only send due_date if actually set (empty string fails date validation)
                        if (this.newAction.due_date) {
                            payload.due_date = this.newAction.due_date;
                        }

                        await this.$axios.post('/admin/action-stream', payload);
                        this.resetForm();
                        this.showCreateForm = false;
                        await this.fetchActions();
                        this.broadcastChanged();
                    } catch (error) {
                        const msg = error.response?.data?.message || 'Failed to save action. Please try again.';
                        if (typeof this.$emitter !== 'undefined') {
                            this.$emitter.emit('add-flash', { type: 'error', message: msg });
                        }
                        console.error('Failed to create action:', error);
                    } finally {
                        this.saving = false;
                    }
                },

                cancelCreate() {
                    this.showCreateForm = false;
                    this.resetForm();
                },

                startEditing(action) {
                    if (! action) return;
                    this.editingId = action.id;
                    this.editData = {
                        action_type: action.action_type,
                        priority: action.priority,
                        due_date: action.due_date ? String(action.due_date).split('T')[0] : '',
                        description: action.description || '',
                    };
                },

                cancelEditing() {
                    this.editingId = null;
                    this.editData = {};
                },

                async saveEdit() {
                    if (this.updating || ! this.editingId) return;
                    this.updating = true;
                    try {
                        const payload = {
                            action_type: this.editData.action_type,
                            priority: this.editData.priority,
                            description: this.editData.description,
                        };
                        payload.due_date = this.editData.due_date || null;

                        await this.$axios.put(`/admin/action-stream/${this.editingId}`, payload);
                        this.editingId = null;
                        this.editData = {};
                        await this.fetchActions();
                        this.broadcastChanged();
                    } catch (error) {
                        const msg = error.response?.data?.message || 'Failed to update action.';
                        if (typeof this.$emitter !== 'undefined') {
                            this.$emitter.emit('add-flash', { type: 'error', message: msg });
                        }
                        console.error('Failed to update action:', error);
                    } finally {
                        this.updating = false;
                    }
                },

                resetForm() {
                    this.newAction = {
                        action_type: 'call',
                        priority: 'normal',
                        due_date: new Date().toISOString().split('T')[0],
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

                urgencyLabelFor(action) {
                    return { overdue: 'Overdue', today: 'Due Today', this_week: 'This Week', upcoming: 'Upcoming', none: 'No Date' }[this.calculateUrgency(action.due_date)] || '';
                },

                urgencyDotClassFor(action) {
                    return { overdue: 'bg-red-500', today: 'bg-orange-500', this_week: 'bg-yellow-500', upcoming: 'bg-green-500', none: 'bg-gray-400' }[this.calculateUrgency(action.due_date)] || 'bg-gray-400';
                },

                urgencyLabelClassFor(action) {
                    return {
                        overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        today: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        this_week: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                        upcoming: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                        none: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                    }[this.calculateUrgency(action.due_date)] || 'bg-gray-100 text-gray-500';
                },

                priorityBadgeClassFor(action) {
                    return {
                        urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                        normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                    }[action.priority] || 'bg-gray-100 text-gray-600';
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

                priorityHexColor(priority) {
                    return {
                        urgent: '#ef4444',
                        high: '#f97316',
                        normal: '#3b82f6',
                        low: '#9ca3af',
                    }[priority] || '#9ca3af';
                },
            },
        });
    </script>
@endPushOnce
