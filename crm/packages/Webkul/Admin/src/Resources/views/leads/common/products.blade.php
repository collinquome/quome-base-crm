{!! view_render_event('admin.leads.create.products.form_controls.before') !!}

<v-product-list :data="products"></v-product-list>

{!! view_render_event('admin.leads.create.products.form_controls.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-product-list-template"
    >
        <div class="flex flex-col gap-4">
            {!! view_render_event('admin.leads.create.products.form_controls.table.before') !!}

            <div class="block w-full overflow-x-auto">
                <!-- Table -->
                <x-admin::table>
                    {!! view_render_event('admin.leads.create.products.form_controls.table.head.before') !!}

                    <!-- Table Head -->
                    <x-admin::table.thead>
                        <x-admin::table.thead.tr>
                            <x-admin::table.th>
                                @lang('admin::app.leads.common.products.product-name')
                            </x-admin::table.th>

                            <x-admin::table.th class="text-center">
                                @lang('admin::app.leads.common.products.quantity')
                            </x-admin::table.th>

                            <x-admin::table.th class="text-center">
                                @lang('admin::app.leads.common.products.price')
                            </x-admin::table.th>

                            <x-admin::table.th class="text-center">
                                @lang('admin::app.leads.common.products.amount')
                            </x-admin::table.th>

                            <x-admin::table.th class="text-right">
                                @lang('admin::app.leads.common.products.action')
                            </x-admin::table.th>
                        </x-admin::table.thead.tr>
                    </x-admin::table.thead>

                    {!! view_render_event('admin.leads.create.products.form_controls.table.head.after') !!}

                    {!! view_render_event('admin.leads.create.products.form_controls.table.body.before') !!}

                    <!-- Table Body -->
                    <x-admin::table.tbody>
                        {!! view_render_event('admin.leads.create.products.form_controls.table.body.product_item.before') !!}

                        <!-- Product Item Vue Component -->
                        <v-product-item
                            v-for='(product, index) in products'
                            :product="product"
                            :key="index"
                            :index="index"
                            @onRemoveProduct="removeProduct($event)"
                        ></v-product-item>

                        {!! view_render_event('admin.leads.create.products.form_controls.table.body.product_item.after') !!}
                    </x-admin::table.tbody>

                    {!! view_render_event('admin.leads.create.products.form_controls.table.body.after') !!}
                </x-admin::table>
            </div>

            {!! view_render_event('admin.leads.create.products.form_controls.table.after') !!}

            <!-- Add New Product Item -->
            <button
                type="button"
                class="flex max-w-max items-center gap-2 text-brandColor"
                @click="addProduct"
            >
                <i class="icon-add text-md !text-brandColor"></i>

                @lang('admin::app.leads.common.products.add-more')
            </button>
        </div>
    </script>

    <script
        type="text/x-template"
        id="v-product-item-template"
    >
        <x-admin::table.thead.tr>
            <!-- Product Name -->
            <x-admin::table.td>
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::lookup
                                ::src="src"
                                ::name="`${inputName}[name]`"
                                ::params="params"
                                :placeholder="trans('admin::app.leads.common.products.product-name')"
                                @on-selected="(product) => addProduct(product)"
                                ::value="{ id: product.product_id, name: product.name }"
                            />

                            <x-admin::form.control-group.control
                                type="hidden"
                                ::name="`${inputName}[product_id]`"
                                v-model="product.product_id"
                                rules="required"
                                :label="trans('admin::app.leads.common.products.product-name')"
                                :placeholder="trans('admin::app.leads.common.products.product-name')"
                            />

                            <x-admin::form.control-group.error ::name="`${inputName}[product_id]`" />
                        </x-admin::form.control-group>
                    </div>

                    <!-- Info Icon (hover for product details) -->
                    <button
                        v-if="product.product_id && (product.description || product.sku)"
                        type="button"
                        class="mt-2 flex h-6 w-6 flex-shrink-0 cursor-help items-center justify-center rounded-full border border-gray-300 text-xs font-semibold text-gray-500 transition-colors hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-blue-900/20"
                        @mouseenter="showSelectedPreview($event)"
                        @mouseleave="hideSelectedPreview"
                        @focus="showSelectedPreview($event)"
                        @blur="hideSelectedPreview"
                        data-testid="product-info-icon"
                        aria-label="Show product details"
                    >
                        i
                    </button>
                </div>
            </x-admin::table.td>

            <!-- Product Quantity -->
            <x-admin::table.td class="text-right">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.control
                        type="inline"
                        ::name="`${inputName}[quantity]`"
                        ::value="product.quantity"
                        rules="required|decimal:4"
                        :label="trans('admin::app.leads.common.products.quantity')"
                        :placeholder="trans('admin::app.leads.common.products.quantity')"
                        @on-change="(event) => product.quantity = event.value"
                        position="center"
                    />
                </x-admin::form.control-group>
            </x-admin::table.td>

            <!-- Price -->
            <x-admin::table.td class="text-right">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.control
                        type="inline"
                        ::name="`${inputName}[price]`"
                        ::value="product.price"
                        rules="required|decimal:4"
                        :label="trans('admin::app.leads.common.products.price')"
                        :placeholder="trans('admin::app.leads.common.products.price')"
                        @on-change="(event) => product.price = event.value"
                        ::value-label="$admin.formatPrice(product.price)"
                        position="center"
                    />
                </x-admin::form.control-group>
            </x-admin::table.td>

            <!-- Amount -->
            <x-admin::table.td class="text-right">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.control
                        type="inline"
                        ::name="`${inputName}[amount]`"
                        ::value="product.price * product.quantity"
                        rules="required|decimal:4"
                        :label="trans('admin::app.leads.common.products.total')"
                        :placeholder="trans('admin::app.leads.common.products.total')"
                        ::value-label="$admin.formatPrice(product.price * product.quantity)"
                        :allowEdit="false"
                        position="center"
                    />
                </x-admin::form.control-group>
            </x-admin::table.td>

            <!-- Action -->
            <x-admin::table.td class="text-right">
                <x-admin::form.control-group >
                    <i
                        @click="removeProduct"
                        class="icon-delete cursor-pointer text-2xl"
                    ></i>
                </x-admin::form.control-group>

                <!-- Selected product hover preview, teleported to body for z-index safety -->
                <Teleport to="body">
                    <div
                        v-if="showingSelectedPreview && product.product_id && (product.description || product.sku)"
                        class="pointer-events-none fixed z-[9999] w-72 rounded-lg border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                        :style="selectedPreviewStyle"
                        data-testid="selected-product-preview"
                    >
                        <p class="font-semibold text-gray-900 dark:text-white" v-text="product.name"></p>
                        <p v-if="product.sku && product.sku !== product.name" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            SKU: @{{ product.sku }}
                        </p>
                        <p v-if="product.description" class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300" v-text="product.description"></p>
                        <p v-if="product.price != null && product.price !== ''" class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                            Price: @{{ $admin?.formatPrice ? $admin.formatPrice(Number(product.price)) : Number(product.price).toFixed(2) }}
                        </p>
                    </div>
                </Teleport>
            </x-admin::table.td>
        </x-admin::table.thead.tr>
    </script>

    <script type="module">
        app.component('v-product-list', {
            template: '#v-product-list-template',

            props: ['data'],

            data: function () {
                return {
                    products: this.data ? this.data : [],
                }
            },

            methods: {
                addProduct() {
                    this.products.push({
                        id: null,
                        product_id: null,
                        name: '',
                        quantity: 1,
                        price: 0,
                        amount: null,
                    })
                },

                removeProduct (product) {
                    const index = this.products.indexOf(product);
                    this.products.splice(index, 1);
                },
            },
        });

        app.component('v-product-item', {
            template: '#v-product-item-template',

            props: ['index', 'product'],

            data() {
                return {
                    products: [],
                    showingSelectedPreview: false,
                    selectedPreviewStyle: { top: '0px', left: '0px' },
                }
            },

            computed: {
                inputName() {
                    if (this.product.id) {
                        return "products[" + this.product.id + "]";
                    }

                    return "products[product_" + this.index + "]";
                },

                src() {
                    return '{{ route('admin.products.search') }}';
                },

                params() {
                    return {
                        params: {
                            query: this.product.name,
                        },
                    };
                },
            },

            methods: {
                /**
                 * Add the product.
                 *
                 * @param {Object} result
                 *
                 * @return {void}
                 */
                addProduct(result) {
                    this.product.product_id = result.id;

                    this.product.name = result.name;

                    this.product.price = result.price;

                    this.product.quantity = result.quantity ?? 1;

                    this.product.description = result.description ?? null;

                    this.product.sku = result.sku ?? null;
                },

                /**
                 * Show the hover preview card for the selected product.
                 *
                 * @param {MouseEvent} event
                 * @return {void}
                 */
                showSelectedPreview(event) {
                    if (! this.product.product_id) return;

                    const rect = event.currentTarget.getBoundingClientRect();
                    const previewWidth = 288;
                    const gap = 8;

                    let left = rect.right + gap;
                    if (left + previewWidth > window.innerWidth) {
                        left = Math.max(8, rect.left - previewWidth - gap);
                    }

                    this.selectedPreviewStyle = {
                        top: `${Math.max(8, rect.top)}px`,
                        left: `${left}px`,
                    };
                    this.showingSelectedPreview = true;
                },

                hideSelectedPreview() {
                    this.showingSelectedPreview = false;
                },

                /**
                 * Remove the product.
                 *
                 * @return {void}
                 */
                removeProduct () {
                    this.$emit('onRemoveProduct', this.product)
                }
            }
        });
    </script>
@endPushOnce