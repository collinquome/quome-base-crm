{!! view_render_event('admin.leads.create.contact_person.form_controls.before') !!}

<v-contact-component :data="person"></v-contact-component>

{!! view_render_event('admin.leads.create.contact_person.form_controls.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-contact-component-template"
    >
        <!-- Person Search Lookup -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label class="required">
                @lang('admin::app.leads.common.contact.name')
            </x-admin::form.control-group.label>

            <x-admin::lookup
                ::src="src"
                name="person[id]"
                ::params="params"
                ::rules="nameValidationRule"
                :label="trans('admin::app.leads.common.contact.name')"
                ::value="{id: person.id, name: person.name}"
                :placeholder="trans('admin::app.leads.common.contact.name')"
                @on-selected="addPerson"
                :can-add-new="true"
            />

            <x-admin::form.control-group.control
                type="hidden"
                name="person[name]"
                v-model="person.name"
                v-if="person.name"
            />

            <x-admin::form.control-group.error control-name="person[id]" />

            <!-- Duplicate-contact suggestion -->
            <div
                v-if="suggestedPerson && !person.id"
                class="mt-2 flex items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm dark:border-amber-700 dark:bg-amber-900/20"
                data-testid="contact-duplicate-suggestion"
            >
                <div class="text-amber-800 dark:text-amber-300">
                    <span class="font-semibold">@{{ suggestedPerson.name }}</span> already has this @{{ suggestedMatchField }} on file.
                </div>
                <button
                    type="button"
                    class="rounded-md border border-amber-500 bg-amber-500 px-3 py-1 text-xs font-semibold text-white transition-colors hover:bg-amber-600"
                    @click="useSuggestedPerson"
                    data-testid="contact-duplicate-use-existing"
                >
                    Use this contact
                </button>
            </div>
        </x-admin::form.control-group>

        <!-- Person Email -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label class="required">
                @lang('admin::app.leads.common.contact.email')
            </x-admin::form.control-group.label>

            <x-admin::attributes.edit.email />

            <v-email-component
                :attribute="{'id': person?.id, 'code': 'person[emails]', 'name': 'Email'}"
                validations="required"
                :value="person.emails"
                :is-disabled="person?.id ? true : false"
            ></v-email-component>
        </x-admin::form.control-group>

        <!-- Person Contact Numbers -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.contact-number')
            </x-admin::form.control-group.label>

            <x-admin::attributes.edit.phone />

            <v-phone-component
                :attribute="{'id': person?.id, 'code': 'person[contact_numbers]', 'name': 'Contact Numbers'}"
                :value="person.contact_numbers"
                :is-disabled="person?.id ? true : false"
            ></v-phone-component>
        </x-admin::form.control-group>

        <!-- Person Organization -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.organization')
            </x-admin::form.control-group.label>

            @php
                $organizationAttribute = app('Webkul\Attribute\Repositories\AttributeRepository')->findOneWhere([
                    'entity_type' => 'persons',
                    'code'        => 'organization_id'
                ]);

                $organizationAttribute->code = 'person[' . $organizationAttribute->code . ']';
            @endphp

            <x-admin::attributes.edit.lookup />

            <v-lookup-component
                :key="person.organization?.id"
                :attribute='@json($organizationAttribute)'
                :value="person.organization"
                :is-disabled="person?.id ? true : false"
                can-add-new="true"
            ></v-lookup-component>
        </x-admin::form.control-group>
    </script>

    <script type="module">
        app.component('v-contact-component', {
            template: '#v-contact-component-template',

            props: ['data'],

            data () {
                return {
                    is_searching: false,

                    person: this.data ? this.data : {
                        'name': ''
                    },

                    persons: [],

                    // Duplicate-contact suggestion state.
                    suggestedPerson: null,
                    suggestedMatchField: '',
                    _duplicateCheckTimer: null,
                }
            },

            computed: {
                src() {
                    return "{{ route('admin.contacts.persons.search') }}";
                },

                params() {
                    return {
                        params: {
                            query: this.person['name']
                        }
                    }
                },

                nameValidationRule() {
                    return this.person.name ? '' : 'required';
                }
            },

            watch: {
                'person.id'() {
                    if (this.person?.id) {
                        this.suggestedPerson = null;
                    }
                },
            },

            mounted() {
                // The email/phone sub-components manage their own internal state
                // and don't propagate two-way to `person.emails` / `person.contact_numbers`.
                // Watch DOM input events on the document instead. v-contact-component
                // may have multiple root nodes (fragment) so $el-scoping is unreliable.
                this._onDomInput = (e) => {
                    const t = e.target;
                    if (! t?.name) return;
                    if (t.name.includes('[emails]') || t.name.includes('[contact_numbers]')) {
                        this.scheduleDuplicateCheck();
                    }
                };
                document.addEventListener('input', this._onDomInput, { passive: true });
                document.addEventListener('change', this._onDomInput, { passive: true });
            },

            beforeUnmount() {
                if (this._onDomInput) {
                    document.removeEventListener('input', this._onDomInput);
                    document.removeEventListener('change', this._onDomInput);
                }
                if (this._duplicateCheckTimer) clearTimeout(this._duplicateCheckTimer);
            },

            methods: {
                addPerson (person) {
                    this.person = person;
                    this.suggestedPerson = null;
                },

                useSuggestedPerson() {
                    if (this.suggestedPerson) {
                        this.addPerson({ ...this.suggestedPerson });
                    }
                },

                scheduleDuplicateCheck() {
                    if (this.person?.id) {
                        this.suggestedPerson = null;
                        return;
                    }
                    if (this._duplicateCheckTimer) clearTimeout(this._duplicateCheckTimer);
                    this._duplicateCheckTimer = setTimeout(() => this.checkForDuplicate(), 500);
                },

                async checkForDuplicate() {
                    // Collect non-empty email/phone values from the live DOM.
                    // Use document scope because the component may be a fragment.
                    const emails = Array.from(document.querySelectorAll('input[name*="[emails]"][name$="[value]"]'))
                        .map(el => el.value.trim()).filter(Boolean);
                    const phones = Array.from(document.querySelectorAll('input[name*="[contact_numbers]"][name$="[value]"]'))
                        .map(el => el.value.trim()).filter(Boolean);

                    for (const email of emails) {
                        if (email.length < 5 || !email.includes('@')) continue;
                        const hit = await this.lookupByValue(email, 'email');
                        if (hit) {
                            this.suggestedPerson = hit;
                            this.suggestedMatchField = 'email';
                            return;
                        }
                    }
                    for (const phone of phones) {
                        if (phone.replace(/\D/g, '').length < 6) continue;
                        const hit = await this.lookupByValue(phone, 'phone');
                        if (hit) {
                            this.suggestedPerson = hit;
                            this.suggestedMatchField = 'phone number';
                            return;
                        }
                    }
                    this.suggestedPerson = null;
                },

                async lookupByValue(value, kind) {
                    try {
                        const response = await this.$axios.get(this.src, {
                            params: { query: value },
                        });
                        const matches = response.data?.data || [];
                        for (const candidate of matches) {
                            if (kind === 'email') {
                                const emailMatch = (candidate.emails || []).some(e => (e?.value || '').toLowerCase() === value.toLowerCase());
                                if (emailMatch) return candidate;
                            } else {
                                const normalized = value.replace(/\D/g, '');
                                const phoneMatch = (candidate.contact_numbers || []).some(p => (p?.value || '').replace(/\D/g, '') === normalized);
                                if (phoneMatch) return candidate;
                            }
                        }
                    } catch (_) {
                        /* network / 500 — silently skip */
                    }
                    return null;
                },
            }
        });
    </script>
@endPushOnce