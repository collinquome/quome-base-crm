@props([
    'addresses' => [],
    'namePrefix' => null,
    'label' => 'Addresses',
    'testidPrefix' => 'address',
])

<v-addresses-repeater
    :initial='@json($addresses ?? [])'
    name-prefix="{{ $namePrefix ?? '' }}"
    testid-prefix="{{ $testidPrefix }}"
    label="{{ $label }}"
></v-addresses-repeater>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-addresses-repeater-template"
    >
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-800 dark:text-white">
                    @{{ label }}
                </label>

                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="addAddress"
                    :data-testid="testidPrefix + '-add'"
                >
                    <span class="icon-add text-base"></span>
                    Add Address
                </button>
            </div>

            <!-- Single hidden payload so the backend can always tell this form
                 handled addresses (even when the user removed all rows). -->
            <input
                type="hidden"
                :name="payloadName"
                :value="JSON.stringify(addresses)"
                :data-testid="testidPrefix + '-payload'"
            >

            <template v-if="!addresses.length">
                <div
                    class="rounded-md border border-dashed border-gray-300 px-4 py-3 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                    :data-testid="testidPrefix + '-empty'"
                >
                    No addresses. Click "Add Address" to add one.
                </div>
            </template>

            <div
                v-for="(address, i) in addresses"
                :key="i"
                class="flex flex-col gap-2 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50"
                :data-testid="testidPrefix + '-row-' + i"
            >
                <div class="flex items-center justify-between gap-2">
                    <select
                        v-model="address.address_type"
                        class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-type-' + i"
                    >
                        <option value="home">Home</option>
                        <option value="work">Work</option>
                        <option value="mailing">Mailing</option>
                        <option value="other">Other</option>
                    </select>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        @click="removeAddress(i)"
                        :data-testid="testidPrefix + '-remove-' + i"
                    >
                        <span class="icon-delete text-base"></span>
                        Remove
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-6">
                    <input
                        type="text"
                        v-model="address.address_line_1"
                        placeholder="Street address"
                        class="col-span-1 rounded-md border-gray-300 text-sm md:col-span-6 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-line1-' + i"
                    >

                    <input
                        type="text"
                        v-model="address.address_line_2"
                        placeholder="Apt, suite, unit (optional)"
                        class="col-span-1 rounded-md border-gray-300 text-sm md:col-span-6 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-line2-' + i"
                    >

                    <input
                        type="text"
                        v-model="address.city"
                        placeholder="City"
                        class="col-span-1 rounded-md border-gray-300 text-sm md:col-span-2 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-city-' + i"
                    >

                    <input
                        type="text"
                        v-model="address.state"
                        placeholder="State"
                        class="col-span-1 rounded-md border-gray-300 text-sm md:col-span-2 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-state-' + i"
                    >

                    <input
                        type="text"
                        v-model="address.postcode"
                        placeholder="ZIP"
                        class="col-span-1 rounded-md border-gray-300 text-sm md:col-span-1 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-postcode-' + i"
                    >

                    <input
                        type="text"
                        v-model="address.country"
                        placeholder="Country"
                        class="col-span-1 rounded-md border-gray-300 text-sm md:col-span-1 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :data-testid="testidPrefix + '-country-' + i"
                    >
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-addresses-repeater', {
            template: '#v-addresses-repeater-template',

            props: {
                initial: { type: [Array, Object, String], default: () => [] },
                namePrefix: { type: String, default: '' },
                testidPrefix: { type: String, default: 'address' },
                label: { type: String, default: 'Addresses' },
            },

            data() {
                let seed = this.initial;

                if (typeof seed === 'string') {
                    try { seed = JSON.parse(seed); } catch (_) { seed = []; }
                }

                if (! Array.isArray(seed)) seed = [];

                return {
                    addresses: seed.map((a) => this.normalize(a)),
                };
            },

            computed: {
                payloadName() {
                    return this.namePrefix
                        ? this.namePrefix + '[addresses_payload]'
                        : 'addresses_payload';
                },
            },

            methods: {
                normalize(a) {
                    return {
                        address_type:   a?.address_type   || 'home',
                        address_line_1: a?.address_line_1 || '',
                        address_line_2: a?.address_line_2 || '',
                        city:           a?.city           || '',
                        state:          a?.state          || '',
                        postcode:       a?.postcode       || '',
                        country:        a?.country        || 'US',
                    };
                },

                addAddress() {
                    this.addresses.push(this.normalize({}));
                },

                removeAddress(i) {
                    this.addresses.splice(i, 1);
                },
            },
        });
    </script>
@endPushOnce
