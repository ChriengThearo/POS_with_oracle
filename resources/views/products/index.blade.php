@extends('layouts.ecommerce')

@section('title', 'Products')

@section('content')
    @php
        use Illuminate\Support\Str;
    @endphp
    <section class="card">
        <div class="actions" style="justify-content: space-between; align-items: flex-start;">
            <div>
                <h1 class="headline">Products</h1>
                <p class="subtle">Manage inventory from <code>PRODUCTS</code> and <code>PRODUCT_TYPE</code>.</p>
            </div>
            <div class="actions">
                <span class="chip">Oracle</span>
                <span class="chip">RBAC</span>
            </div>
        </div>

        <div class="grid grid-3" style="margin-top: 14px;">
            <article class="stat">
                <div class="label">Products</div>
                <div class="value">{{ number_format((int) $metrics['products']) }}</div>
            </article>
            <article class="stat">
                <div class="label">Product Types</div>
                <div class="value">{{ number_format((int) $metrics['types']) }}</div>
            </article>
            <article class="stat">
                <div class="label">Understock</div>
                <div class="value">{{ number_format((int) $metrics['understock']) }}</div>
            </article>
        </div>

        <div class="field-grid" style="margin-top: 14px;">
            <div>
                <label for="q">Search product / code / type</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="e.g. P0001 or Laptop" autocomplete="off">
            </div>
            <div>
                <label for="type">Product type</label>
                <select id="type" name="type">
                    <option value="">All types</option>
                    @foreach($types as $item)
                        <option value="{{ $item->id }}" @selected((string) $type === (string) $item->id)>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top: 14px;">
            <div class="actions" style="justify-content: space-between; align-items: center;">
                <div class="actions">
                    <button type="button" class="btn btn-primary" id="show-product-list">Product List</button>
                    @if($canManageProducts)
                        <button type="button" class="btn btn-muted" id="show-product-add">Add Product</button>
                    @endif
                    <button type="button" class="btn btn-muted" id="show-product-types">Product Types</button>
                    @if($canManageStockStatus)
                        <button type="button" class="btn btn-muted" id="show-alert-stocks">Alert Stock</button>
                    @endif
                </div>
            </div>
        </section>

    <div class="grid">
        <section class="card" id="product-list-panel">
            <div class="actions" style="justify-content: space-between;">
                <h2 style="margin-top: 0;">Product List</h2>
                <span class="chip">{{ $products->total() }} total</span>
            </div>
            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Pricing</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        @php
                            $profitDisplay = (float) $product->profit_percent;
                            if ($profitDisplay > 0 && $profitDisplay <= 1) {
                                $profitDisplay *= 100;
                            }
                            $photoPath = (string) ($product->photo_path ?? '');
                            $isHttpPhoto = $photoPath !== '' && Str::startsWith($photoPath, ['http://', 'https://']);
                            $isLocalPhoto = $photoPath !== '' && Str::startsWith($photoPath, ['images/', '/images/']);
                            $photoUrl = $isHttpPhoto
                                ? $photoPath
                                : ($isLocalPhoto ? asset(ltrim($photoPath, '/')) : '');
                            $detailPayload = [
                                'product_no' => (string) $product->product_no,
                                'product_name' => (string) $product->product_name,
                                'product_type_id' => (string) $product->product_type_id,
                                'sell_price' => (float) $product->sell_price,
                                'cost_price' => (float) $product->cost_price,
                                'profit_percent' => (float) $profitDisplay,
                                'qty_on_hand' => (int) $product->qty_on_hand,
                                'unit_measure' => (string) $product->unit_measure,
                                'stock_status' => (string) ($product->stock_status ?? ''),
                                'photo_url' => (string) $photoUrl,
                            ];
                        @endphp
                        <tr data-type-id="{{ $product->product_type_id }}">
                            <td>
                                @if($photoUrl !== '')
                                    <img src="{{ $photoUrl }}" alt="Product photo" style="width: 46px; height: 46px; border-radius: 10px; object-fit: cover;">
                                @else
                                    <span class="subtle">No photo</span>
                                @endif
                            </td>
                            <td>{{ $product->product_name }}</td>
                            <td class="product-type-cell">{{ $product->product_type_name ?? 'N/A' }}</td>
                            <td>
                                <div>Sell Price: ${{ number_format((float) $product->sell_price, 2) }}</div>
                                <div class="subtle">Cost Price: ${{ number_format((float) $product->cost_price, 2) }}</div>
                                <div class="subtle">Profit: {{ number_format($profitDisplay, 2) }}%</div>
                            </td>
                            <td>
                                {{ $product->qty_on_hand }} Pieces
                            </td>
                            <td>{{ $product->stock_status ?? 'N/A' }}</td>
                            <td>
                                @if($canManageProducts)
                                    <button
                                        type="button"
                                        class="btn btn-muted js-product-detail"
                                        data-product='@json($detailPayload)'
                                    >
                                        Detail
                                    </button>
                                @else
                                    <span class="subtle">Read only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="subtle">No products found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pager" style="margin-top: 12px;">
                {{ $products->links('pagination.orbit') }}
            </div>
        </section>

        @if($canManageStockStatus)
            <section class="card" id="alert-stocks-panel" style="display: none;">
                <div class="actions" style="justify-content: space-between;">
                    <h2 style="margin-top: 0;">Alert Stock</h2>
                    <span class="chip">{{ $alertStocks->count() }} items</span>
                </div>

                <div class="table-wrap" style="margin-top: 12px;">
                    <table>
                        <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Product Name</th>
                            <th style="text-align: right;">Lower Qty</th>
                            <th style="text-align: right;">Higher Qty</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($alertStocks as $alert)
                            @php
                                $profitDisplay = (float) $alert->profit_percent;
                                if ($profitDisplay > 0 && $profitDisplay <= 1) {
                                    $profitDisplay *= 100;
                                }
                                $photoPath = (string) ($alert->photo_path ?? '');
                                $isHttpPhoto = $photoPath !== '' && Str::startsWith($photoPath, ['http://', 'https://']);
                                $isLocalPhoto = $photoPath !== '' && Str::startsWith($photoPath, ['images/', '/images/']);
                                $photoUrl = $isHttpPhoto
                                    ? $photoPath
                                    : ($isLocalPhoto ? asset(ltrim($photoPath, '/')) : '');
                                $hasAlert = $alert->alert_stock_no !== null;
                                $detailPayload = [
                                    'alert_stock_no' => $hasAlert ? (int) $alert->alert_stock_no : null,
                                    'product_no' => (string) $alert->product_no,
                                    'product_name' => (string) $alert->product_name,
                                    'product_type_id' => (string) $alert->product_type_id,
                                    'sell_price' => (float) $alert->sell_price,
                                    'cost_price' => (float) $alert->cost_price,
                                    'profit_percent' => (float) $profitDisplay,
                                    'lower_qty' => $hasAlert ? (float) $alert->lower_qty : null,
                                    'higher_qty' => $hasAlert ? (float) $alert->higher_qty : null,
                                    'unit_measure' => (string) $alert->unit_measure,
                                    'stock_status' => (string) ($alert->stock_status ?? ''),
                                    'photo_url' => (string) $photoUrl,
                                ];
                            @endphp
                            <tr
                                data-product-no="{{ $alert->product_no }}"
                                data-alert-stock-no="{{ $hasAlert ? (int) $alert->alert_stock_no : '' }}"
                            >
                                <td>
                                    @if($photoUrl !== '')
                                        <img src="{{ $photoUrl }}" alt="Product photo" style="width: 46px; height: 46px; border-radius: 10px; object-fit: cover;">
                                    @else
                                        <span class="subtle">No photo</span>
                                    @endif
                                </td>
                                <td>{{ $alert->product_name }}</td>
                                <td style="text-align: right;">
                                    <input
                                        type="number"
                                        min="0"
                                        class="alert-inline-lower"
                                        value="{{ $hasAlert ? (int) $alert->lower_qty : '' }}"
                                        placeholder="-"
                                        style="width: 90px; text-align: right;"
                                    >
                                </td>
                                <td style="text-align: right;">
                                    <input
                                        type="number"
                                        min="0"
                                        class="alert-inline-higher"
                                        value="{{ $hasAlert ? (int) $alert->higher_qty : '' }}"
                                        placeholder="-"
                                        style="width: 90px; text-align: right;"
                                    >
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-muted js-product-detail"
                                        data-mode="alert"
                                        data-product='@json($detailPayload)'
                                    >
                                        Detail
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary js-alert-inline-save"
                                        style="display: none;"
                                    >
                                        Save
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="subtle">No alert stock records.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($canManageProducts)
        <section class="card" id="product-add-panel" style="display: none;">
            <h2 style="margin-top: 0;">Add Product</h2>
            <p class="subtle">Create a new product record.</p>
            <div id="add-product-message" style="display:none; margin-top:10px;"></div>
            <form id="add-product-form" method="POST" action="{{ route('products.store') }}" class="field-grid" style="margin-top: 12px;" enctype="multipart/form-data">
                @csrf
                <div>
                    <label for="product_no">Product Code</label>
                    <input id="product_no" name="product_no" type="text" value="{{ $nextProductCode }}" required>
                </div>
                <div>
                    <label for="product_name">Product Name</label>
                    <input id="product_name" name="product_name" type="text" required>
                </div>
                <div>
                    <label for="product_photo">Photo</label>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                        <input id="product_photo" name="photo" type="file" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-muted" onclick="document.getElementById('product_photo').click()">Add Photo</button>
                        <span id="add-photo-filename" class="subtle" style="font-size: 0.85em;"></span>
                    </div>
                </div>
                <div>
                    <label for="product_type">Product Type</label>
                    <select id="product_type" name="product_type" required>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="unit_measure">Unit Measure</label>
                    <input id="unit_measure" name="unit_measure" type="text" value="PCS" required>
                </div>
                <div>
                    <label for="sell_price">Sell Price</label>
                    <input id="sell_price" name="sell_price" type="number" step="0.01" min="0" required>
                </div>
                <div>
                    <label for="cost_price">Cost Price</label>
                    <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" required>
                </div>
                <div>
                    <label for="profit_percent">Profit %</label>
                    <input id="profit_percent" name="profit_percent" type="number" step="0.01" min="0" value="0" readonly required>
                </div>
                <div>
                    <label for="qty_on_hand">Qty on Hand</label>
                    <input id="qty_on_hand" name="qty_on_hand" type="number" min="0" value="0" required>
                </div>
                <input id="status" name="status" type="hidden" value="">
                <div class="actions" style="align-items: end;">
                    <button type="submit" id="add-product-btn" class="btn btn-primary">Create</button>
                </div>
            </form>
        </section>
        @endif

        <div id="type-save-confirm-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:100; align-items:center; justify-content:center;">
            <div class="card" style="max-width:380px; width:100%; margin:auto;">
                <h3 style="margin-top:0;">Are you sure?</h3>
                <p id="type-save-confirm-desc" class="subtle" style="margin-bottom:20px;"></p>
                <div class="actions" style="justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn btn-muted" id="type-save-cancel-btn">Cancel</button>
                    <button type="button" class="btn btn-primary" id="type-save-confirm-btn">Yes</button>
                </div>
            </div>
        </div>

        <section class="card" id="product-types-panel" style="display: none;">
            <div style="margin-top: 18px;">
                <h3 style="margin-top: 0;">Product Types</h3>
                <p class="subtle">Manage product type names.</p>
            </div>

            @if($canManageTypes)
                <div class="field-grid" style="margin-top: 12px; grid-template-columns: 1fr 1fr auto;" id="add-type-form-wrap">
                    <div>
                        <label for="type_name_input">Type name</label>
                        <input id="type_name_input" type="text">
                    </div>
                    <div>
                        <label for="type_remarks_input">Remarks <span class="subtle">(optional)</span></label>
                        <input id="type_remarks_input" type="text" maxlength="255">
                    </div>
                    <div class="actions" style="align-items: end;">
                        <button type="button" class="btn btn-primary" id="add-type-btn">Add Type</button>
                    </div>
                </div>
                <div id="type-form-msg" style="display:none; margin-top: 8px;"></div>
            @endif

            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="product-types-tbody">
                    @forelse($types as $type)
                        <tr id="type-row-{{ $type->id }}" data-id="{{ $type->id }}">
                            <td>{{ $type->id }}</td>
                            <td>
                                @if($canManageTypes)
                                    <input
                                        type="text"
                                        value="{{ $type->name }}"
                                        data-original="{{ $type->name }}"
                                        class="type-name-input"
                                        data-id="{{ $type->id }}"
                                        data-update-url="{{ route('product-types.update', ['typeId' => (int) $type->id]) }}"
                                    >
                                @else
                                    {{ $type->name }}
                                @endif
                            </td>
                            <td>
                                @if($canManageTypes)
                                    <input
                                        type="text"
                                        value="{{ $type->remarks ?? '' }}"
                                        data-original="{{ $type->remarks ?? '' }}"
                                        class="type-remarks-input"
                                        data-id="{{ $type->id }}"
                                        maxlength="255"
                                        placeholder="—"
                                    >
                                @else
                                    {{ $type->remarks ?? '—' }}
                                @endif
                            </td>
                            <td>
                                @if($canManageTypes)
                                    <button type="button" class="btn btn-primary type-save-btn" data-id="{{ $type->id }}" style="display:none;">Save</button>
                                    <button type="button" class="btn btn-muted type-delete-btn" data-id="{{ $type->id }}" data-delete-url="{{ route('product-types.delete', ['typeId' => (int) $type->id]) }}">Delete</button>
                                @else
                                    <span class="subtle">Read only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr id="types-empty-row">
                            <td colspan="4" class="subtle">No product types found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if($canManageProducts || $canManageStockStatus)
    <div id="product-detail-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 50; padding: 24px; overflow-y: auto;">
        <div class="card" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title" style="max-width: 860px; margin: 0 auto;">
            <div class="actions" style="justify-content: space-between;">
                <div>
                    <h2 id="detail-modal-title" style="margin-top: 0;">Product Detail</h2>
                    <p class="subtle" style="margin-top: 6px;">Review and update product information.</p>
                </div>
                <span class="chip" id="detail-product-code">#</span>
            </div>

            <div id="product-alert-view" style="display: none; margin-top: 12px;">
                <div class="grid grid-2">
                    <div>
                        <label>Photo</label>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <img id="alert-product-photo" src="" alt="Product photo" style="width: 72px; height: 72px; border-radius: 12px; object-fit: cover; display: none;">
                            <span class="subtle" id="alert-product-photo-empty">No photo</span>
                        </div>
                    </div>
                    <div>
                        <label>Product Name</label>
                        <div id="alert-product-name" class="subtle" style="padding: 9px 10px; border: 1px solid #cbd8e6; border-radius: 10px; background: #fff;"></div>
                    </div>
                    <div>
                        <label>Lower Qty</label>
                        <input id="alert-product-lower" name="lower_qty" type="number" min="0" form="alert-stock-form" required>
                    </div>
                    <div>
                        <label>Higher Qty</label>
                        <input id="alert-product-higher" name="higher_qty" type="number" min="0" form="alert-stock-form" required>
                    </div>
                </div>

                <form
                    id="alert-stock-form"
                    method="POST"
                    data-update-template="{{ route('alert-stocks.update', ['alertStockNo' => '__ALERT__']) }}"
                    data-store-url="{{ route('alert-stocks.store') }}"
                    style="margin-top: 12px;"
                >
                    @csrf
                    <input type="hidden" name="_method" id="alert-form-method" value="PATCH">
                    <input type="hidden" name="product_no" id="alert-form-product-no" value="">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Save Change</button>
                        <button type="button" class="btn btn-muted" id="close-alert-detail">Cancel</button>
                    </div>
                </form>
            </div>

            <form
                id="product-detail-form"
                method="POST"
                data-update-template="{{ route('products.update', ['productNo' => '__PRODUCT__']) }}"
                style="margin-top: 12px;"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PATCH')

                <div class="grid grid-2" id="detail-edit-fields">
                    <div style="grid-column: 1 / -1;">
                        <label>Photo</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                            <button type="button" id="carousel-prev" class="btn btn-muted" style="flex-shrink: 0; font-size: 1.6em; padding: 6px 16px; line-height: 1; display: none;">&#8249;</button>
                            <div style="flex: 1; display: flex; align-items: center; justify-content: center; min-height: 240px; background: #f3f4f6; border-radius: 14px; overflow: hidden;">
                                <img id="detail-product-photo" src="" alt="Product photo" style="max-width: 100%; max-height: 240px; object-fit: contain; display: none; border-radius: 14px;">
                                <span class="subtle" id="detail-product-photo-empty">No photo</span>
                            </div>
                            <button type="button" id="carousel-next" class="btn btn-muted" style="flex-shrink: 0; font-size: 1.6em; padding: 6px 16px; line-height: 1; display: none;">&#8250;</button>
                        </div>
                        <div id="carousel-indicator" style="text-align: center; font-size: 0.82em; color: #888; margin-top: 5px;"></div>
                        <div id="carousel-photo-actions" style="display: none; justify-content: center; gap: 8px; margin-top: 8px;">
                            <button type="button" id="btn-set-default-photo" class="btn btn-muted">Set as Default</button>
                            <button type="button" id="btn-delete-photo" class="btn btn-muted" style="color: #dc2626;">Delete Photo</button>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                            <input id="detail-product-photo-input" type="file" accept="image/*" style="display: none;">
                            <button type="button" class="btn btn-muted" onclick="document.getElementById('detail-product-photo-input').click()">Add Photo</button>
                            <span id="detail-photo-filename" class="subtle" style="font-size: 0.85em;"></span>
                        </div>
                    </div>
                    <div>
                        <label>Product Code</label>
                        <input id="detail-product-no" type="text" readonly>
                    </div>
                    <div>
                        <label for="detail-product-name">Product Name</label>
                        <input id="detail-product-name" name="product_name" type="text" required>
                    </div>
                    <div>
                        <label for="detail-product-type">Product Type</label>
                        <select id="detail-product-type" name="product_type" required>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="detail-sell-price">Sell Price</label>
                        <input id="detail-sell-price" name="sell_price" type="number" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label for="detail-cost-price">Cost Price</label>
                        <input id="detail-cost-price" name="cost_price" type="number" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label for="detail-profit-percent">Profit %</label>
                        <input id="detail-profit-percent" name="profit_percent" type="number" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label for="detail-qty">Stock (Pieces)</label>
                        <input id="detail-qty" name="qty_on_hand" type="number" min="0" required>
                    </div>
                    <div>
                        <label for="detail-unit">Unit Measure</label>
                        <input id="detail-unit" name="unit_measure" type="text" readonly>
                    </div>
                    <div>
                        <label for="detail-status">Status</label>
                        <input id="detail-status" name="status" type="text" readonly>
                    </div>
                </div>

                <div class="actions" style="margin-top: 12px;">
                    <button type="submit" class="btn btn-primary">Save Change</button>
                    <button type="button" class="btn btn-muted" id="cancel-product-detail">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const listBtn = document.getElementById('show-product-list');
            const addBtn = document.getElementById('show-product-add');
            const typesBtn = document.getElementById('show-product-types');
            const alertBtn = document.getElementById('show-alert-stocks');
            const listPanel = document.getElementById('product-list-panel');
            const addPanel = document.getElementById('product-add-panel');
            const typesPanel = document.getElementById('product-types-panel');
            const alertPanel = document.getElementById('alert-stocks-panel');
            const detailModal = document.getElementById('product-detail-modal');
            const detailForm = document.getElementById('product-detail-form');
            const detailEditFields = document.getElementById('detail-edit-fields');
            const alertView = document.getElementById('product-alert-view');
            const alertForm = document.getElementById('alert-stock-form');
            const detailCode = document.getElementById('detail-product-code');
            const detailPhoto = document.getElementById('detail-product-photo');
            const detailPhotoEmpty = document.getElementById('detail-product-photo-empty');
            const carouselPrev = document.getElementById('carousel-prev');
            const carouselNext = document.getElementById('carousel-next');
            const carouselIndicator = document.getElementById('carousel-indicator');
            const carouselPhotoActions = document.getElementById('carousel-photo-actions');
            const btnSetDefault = document.getElementById('btn-set-default-photo');
            const btnDeletePhoto = document.getElementById('btn-delete-photo');
            const cancelDetail = document.getElementById('cancel-product-detail');

            // Each item: { photo_id: int, url: string }
            let carouselPhotos = [];
            let carouselIndex = 0;
            let currentProductNo = '';
            let currentRowThumbnail = null; // <img> in the product list row that opened the modal
            let currentAlertRow = null;     // <tr> in the alert stock table that opened the modal
            let currentAlertButton = null;  // the Detail button that opened the alert modal

            function showCarouselPhoto(index) {
                if (!detailPhoto || !detailPhotoEmpty) return;
                if (carouselPhotos.length === 0) {
                    detailPhoto.src = '';
                    detailPhoto.style.display = 'none';
                    detailPhotoEmpty.style.display = '';
                    if (carouselPrev) carouselPrev.style.display = 'none';
                    if (carouselNext) carouselNext.style.display = 'none';
                    if (carouselIndicator) carouselIndicator.textContent = '';
                    if (carouselPhotoActions) carouselPhotoActions.style.display = 'none';
                    return;
                }
                carouselIndex = Math.max(0, Math.min(index, carouselPhotos.length - 1));
                detailPhoto.src = carouselPhotos[carouselIndex].url;
                detailPhoto.style.display = '';
                detailPhotoEmpty.style.display = 'none';
                const hasMany = carouselPhotos.length > 1;
                if (carouselPrev) carouselPrev.style.display = hasMany ? '' : 'none';
                if (carouselNext) carouselNext.style.display = hasMany ? '' : 'none';
                if (carouselIndicator) carouselIndicator.textContent = hasMany ? `${carouselIndex + 1} / ${carouselPhotos.length}` : '';
                if (carouselPhotoActions) carouselPhotoActions.style.display = 'flex';
                // Show/hide Set as Default: hide it if this is already the first (default) photo
                if (btnSetDefault) btnSetDefault.style.display = carouselIndex === 0 ? 'none' : '';
            }

            function updateRowThumbnail(url) {
                if (!currentRowThumbnail) return;
                if (url) {
                    currentRowThumbnail.src = url;
                    currentRowThumbnail.style.display = '';
                } else {
                    currentRowThumbnail.src = '';
                    currentRowThumbnail.style.display = 'none';
                }
            }

            if (carouselPrev) carouselPrev.addEventListener('click', () => showCarouselPhoto(carouselIndex - 1));
            if (carouselNext) carouselNext.addEventListener('click', () => showCarouselPhoto(carouselIndex + 1));

            if (btnDeletePhoto) {
                btnDeletePhoto.addEventListener('click', () => {
                    if (carouselPhotos.length === 0) return;
                    const photo = carouselPhotos[carouselIndex];
                    if (!confirm('Delete this photo?')) return;
                    fetch(`/products/${encodeURIComponent(currentProductNo)}/photos/${photo.photo_id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r => r.ok ? r.json() : Promise.reject(r))
                      .then(() => {
                          const wasDefault = carouselIndex === 0;
                          carouselPhotos.splice(carouselIndex, 1);
                          showCarouselPhoto(Math.min(carouselIndex, carouselPhotos.length - 1));
                          if (wasDefault) {
                              updateRowThumbnail(carouselPhotos.length > 0 ? carouselPhotos[0].url : null);
                          }
                      })
                      .catch(() => alert('Failed to delete photo.'));
                });
            }

            if (btnSetDefault) {
                btnSetDefault.addEventListener('click', () => {
                    if (carouselPhotos.length === 0 || carouselIndex === 0) return;
                    const photo = carouselPhotos[carouselIndex];
                    fetch(`/products/${encodeURIComponent(currentProductNo)}/photos/${photo.photo_id}/default`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r => r.ok ? r.json() : Promise.reject(r))
                      .then(() => {
                          // Re-fetch to get fresh photo_ids after DB reinsert
                          return fetch(`/products/${encodeURIComponent(currentProductNo)}/photos`, {
                              headers: { 'X-Requested-With': 'XMLHttpRequest' }
                          });
                      })
                      .then(r => r.ok ? r.json() : Promise.reject(r))
                      .then(json => {
                          if (json && Array.isArray(json.photos) && json.photos.length > 0) {
                              carouselPhotos = json.photos;
                              showCarouselPhoto(0);
                              updateRowThumbnail(carouselPhotos[0].url);
                          }
                      })
                      .catch(() => alert('Failed to set default photo.'));
                });
            }
            const closeAlertDetail = document.getElementById('close-alert-detail');
            const alertPhoto = document.getElementById('alert-product-photo');
            const alertPhotoEmpty = document.getElementById('alert-product-photo-empty');
            const alertName = document.getElementById('alert-product-name');
            const alertLower = document.getElementById('alert-product-lower');
            const alertHigher = document.getElementById('alert-product-higher');
            const modalTitle = document.getElementById('detail-modal-title');
            const sellPriceInput = document.getElementById('sell_price');
            const costPriceInput = document.getElementById('cost_price');
            const profitInput = document.getElementById('profit_percent');

            if (!listBtn || !listPanel || !typesBtn || !typesPanel) return;

            const activate = (target) => {
                const showList = target === 'list';
                const showAdd = target === 'add';
                const showTypes = target === 'types';
                const showAlerts = target === 'alerts';
                listPanel.style.display = showList ? '' : 'none';
                if (addPanel) addPanel.style.display = showAdd ? '' : 'none';
                typesPanel.style.display = showTypes ? '' : 'none';
                if (alertPanel) alertPanel.style.display = showAlerts ? '' : 'none';
                if (detailModal) {
                    detailModal.style.display = 'none';
                }

                listBtn.classList.toggle('btn-primary', showList);
                listBtn.classList.toggle('btn-muted', !showList);
                if (addBtn) {
                    addBtn.classList.toggle('btn-primary', showAdd);
                    addBtn.classList.toggle('btn-muted', !showAdd);
                }
                typesBtn.classList.toggle('btn-primary', showTypes);
                typesBtn.classList.toggle('btn-muted', !showTypes);
                if (alertBtn) {
                    alertBtn.classList.toggle('btn-primary', showAlerts);
                    alertBtn.classList.toggle('btn-muted', !showAlerts);
                }
            };

            listBtn.addEventListener('click', () => activate('list'));
            if (addBtn) addBtn.addEventListener('click', () => activate('add'));
            typesBtn.addEventListener('click', () => activate('types'));
            if (alertBtn) alertBtn.addEventListener('click', () => activate('alerts'));

            const detailButtons = document.querySelectorAll('.js-product-detail');
            const updateTemplate = detailForm ? detailForm.dataset.updateTemplate || '' : '';

            detailButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (!detailModal || !detailForm) return;
                    const payload = button.getAttribute('data-product');
                    if (!payload) return;
                    let data = {};
                    try {
                        data = JSON.parse(payload);
                    } catch (error) {
                        return;
                    }

                    const mode = button.getAttribute('data-mode') || 'product';
                    if (modalTitle) {
                        modalTitle.textContent = mode === 'alert' ? 'Alert Stock Detail' : 'Product Detail';
                    }

                    const productNo = data.product_no || '';
                    currentProductNo = productNo;
                    currentRowThumbnail = button.closest('tr')?.querySelector('img') || null;
                    currentAlertRow = mode === 'alert' ? (button.closest('tr') || null) : null;
                    currentAlertButton = mode === 'alert' ? button : null;
                    if (detailForm) {
                        detailForm.action = updateTemplate.replace('__PRODUCT__', encodeURIComponent(productNo));
                    }

                    const setValue = (id, value) => {
                        const input = document.getElementById(id);
                        if (input) {
                            input.value = value ?? '';
                        }
                    };

                    setValue('detail-product-no', productNo);
                    setValue('detail-product-name', data.product_name || '');
                    setValue('detail-sell-price', data.sell_price ?? '');
                    setValue('detail-cost-price', data.cost_price ?? '');
                    setValue('detail-profit-percent', data.profit_percent ?? '');
                    setValue('detail-qty', data.qty_on_hand ?? '');
                    setValue('detail-unit', data.unit_measure || '');
                    setValue('detail-status', data.stock_status || '');

                    const typeSelect = document.getElementById('detail-product-type');
                    if (typeSelect) {
                        typeSelect.value = data.product_type_id ?? '';
                    }

                    if (detailCode) {
                        detailCode.textContent = productNo ? `#${productNo}` : '#';
                    }

                    // Reset carousel, show placeholder immediately from cached data
                    carouselPhotos = data.photo_url ? [{ photo_id: 0, url: data.photo_url }] : [];
                    carouselIndex = 0;
                    showCarouselPhoto(0);

                    // Fetch all photos (with IDs) for this product
                    fetch(`/products/${encodeURIComponent(productNo)}/photos`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(r => r.ok ? r.json() : null)
                        .then(json => {
                            if (json && Array.isArray(json.photos) && json.photos.length > 0) {
                                carouselPhotos = json.photos; // [{photo_id, url}, ...]
                                showCarouselPhoto(0);
                            }
                        })
                        .catch(() => {});

                    if (alertView && detailEditFields) {
                        const showAlert = mode === 'alert';
                        alertView.style.display = showAlert ? '' : 'none';
                        detailEditFields.style.display = showAlert ? 'none' : '';
                        if (detailForm) {
                            detailForm.style.display = showAlert ? 'none' : '';
                        }
                        if (showAlert) {
                            if (alertPhoto && alertPhotoEmpty) {
                                const photoUrl = data.photo_url || '';
                                if (photoUrl) {
                                    alertPhoto.src = photoUrl;
                                    alertPhoto.style.display = '';
                                    alertPhotoEmpty.style.display = 'none';
                                } else {
                                    alertPhoto.src = '';
                                    alertPhoto.style.display = 'none';
                                    alertPhotoEmpty.style.display = '';
                                }
                            }
                            if (alertName) alertName.textContent = data.product_name || '';
                            if (alertLower) alertLower.value = data.lower_qty ?? '';
                            if (alertHigher) alertHigher.value = data.higher_qty ?? '';
                            if (alertForm) {
                                const hasRecord = data.alert_stock_no !== null && data.alert_stock_no !== undefined;
                                const methodInput = document.getElementById('alert-form-method');
                                const productNoInput = document.getElementById('alert-form-product-no');
                                if (hasRecord) {
                                    const alertTemplate = alertForm.dataset.updateTemplate || '';
                                    alertForm.action = alertTemplate.replace('__ALERT__', encodeURIComponent(data.alert_stock_no));
                                    if (methodInput) methodInput.value = 'PATCH';
                                } else {
                                    alertForm.action = alertForm.dataset.storeUrl || '';
                                    if (methodInput) methodInput.value = '';
                                }
                                if (productNoInput) productNoInput.value = data.product_no || '';
                            }
                        }
                    }

                    detailModal.style.display = '';
                });
            });

            if (cancelDetail) {
                cancelDetail.addEventListener('click', () => {
                    if (!detailModal) return;
                    detailModal.style.display = 'none';
                });
            }

            if (closeAlertDetail) {
                closeAlertDetail.addEventListener('click', () => {
                    if (!detailModal) return;
                    detailModal.style.display = 'none';
                });
            }

            if (alertForm) {
                alertForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const fd = new FormData(alertForm);
                    const csrf = document.querySelector('input[name="_token"]')?.value || '';
                    fetch(alertForm.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                        .then(r => r.json().then(json => ({ ok: r.ok, json })))
                        .then(({ ok, json }) => {
                            if (!ok) { alert(json.error || 'Failed to save.'); return; }
                            // Update the inline inputs in the row so they match the saved values
                            if (currentAlertRow) {
                                const rowLower  = currentAlertRow.querySelector('.alert-inline-lower');
                                const rowHigher = currentAlertRow.querySelector('.alert-inline-higher');
                                if (rowLower)  { rowLower.value  = json.lower_qty;  rowLower.dataset.committed  = String(json.lower_qty); }
                                if (rowHigher) { rowHigher.value = json.higher_qty; rowHigher.dataset.committed = String(json.higher_qty); }
                                if (currentAlertRow.dataset.alertStockNo === '' && json.alert_stock_no) {
                                    currentAlertRow.dataset.alertStockNo = json.alert_stock_no;
                                }
                            }
                            // Sync data-product on the button so next open shows fresh values
                            if (currentAlertButton) {
                                try {
                                    const payload = JSON.parse(currentAlertButton.getAttribute('data-product') || '{}');
                                    payload.lower_qty = json.lower_qty;
                                    payload.higher_qty = json.higher_qty;
                                    if (json.alert_stock_no) payload.alert_stock_no = json.alert_stock_no;
                                    currentAlertButton.setAttribute('data-product', JSON.stringify(payload));
                                } catch (_) {}
                            }
                            if (detailModal) detailModal.style.display = 'none';
                        })
                        .catch(() => alert('Failed to save.'));
                });
            }

            if (detailModal) {
                detailModal.addEventListener('click', (event) => {
                    if (event.target === detailModal) {
                        detailModal.style.display = 'none';
                    }
                });
            }

            // Inline alert stock editing
            document.querySelectorAll('#alert-stocks-panel tbody tr').forEach(row => {
                const lowerInput  = row.querySelector('.alert-inline-lower');
                const higherInput = row.querySelector('.alert-inline-higher');
                const detailBtn   = row.querySelector('.js-product-detail');
                const saveBtn     = row.querySelector('.js-alert-inline-save');
                if (!lowerInput || !higherInput || !saveBtn) return;

                // Tracks the last saved/committed values — stored on the element so modal save can update them
                lowerInput.dataset.committed  = lowerInput.value;
                higherInput.dataset.committed = higherInput.value;

                const cancel = () => {
                    lowerInput.value  = lowerInput.dataset.committed;
                    higherInput.value = higherInput.dataset.committed;
                    if (detailBtn) detailBtn.style.display = '';
                    saveBtn.style.display = 'none';
                };

                const markDirty = () => {
                    const dirty = lowerInput.value !== lowerInput.dataset.committed || higherInput.value !== higherInput.dataset.committed;
                    if (detailBtn) detailBtn.style.display = dirty ? 'none' : '';
                    saveBtn.style.display = dirty ? '' : 'none';
                };

                lowerInput.addEventListener('input', markDirty);
                higherInput.addEventListener('input', markDirty);

                // Cancel immediately when user clicks outside this row
                document.addEventListener('mousedown', (e) => {
                    if (saveBtn.style.display !== 'none' && !row.contains(e.target)) {
                        cancel();
                    }
                });

                saveBtn.addEventListener('click', () => {
                    const productNo    = row.dataset.productNo || '';
                    const alertStockNo = row.dataset.alertStockNo || '';
                    const lower  = parseInt(lowerInput.value, 10);
                    const higher = parseInt(higherInput.value, 10);
                    if (isNaN(lower) || isNaN(higher)) { alert('Please enter valid numbers.'); return; }
                    if (higher < lower) { alert('Higher Qty must be >= Lower Qty.'); return; }

                    const csrf = document.querySelector('input[name="_token"]')?.value || '';
                    const isNew = alertStockNo === '';
                    const url = isNew
                        ? '{{ route('alert-stocks.store') }}'
                        : `/alert-stocks/${encodeURIComponent(alertStockNo)}`;

                    const fd = new FormData();
                    fd.append('_token', csrf);
                    if (!isNew) fd.append('_method', 'PATCH');
                    if (isNew)  fd.append('product_no', productNo);
                    fd.append('lower_qty',  lower);
                    fd.append('higher_qty', higher);

                    saveBtn.disabled = true;
                    fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                        .then(r => r.json().then(json => ({ ok: r.ok, json })))
                        .then(({ ok, json }) => {
                            if (!ok) { alert(json.error || 'Failed to save.'); return; }
                            // Update committed values so cancel reverts to the new saved state
                            lowerInput.dataset.committed  = String(json.lower_qty);
                            higherInput.dataset.committed = String(json.higher_qty);
                            lowerInput.value  = String(json.lower_qty);
                            higherInput.value = String(json.higher_qty);
                            // Sync data-product on the Detail button so modal shows fresh values
                            if (detailBtn) {
                                try {
                                    const p = JSON.parse(detailBtn.getAttribute('data-product') || '{}');
                                    p.lower_qty = json.lower_qty;
                                    p.higher_qty = json.higher_qty;
                                    if (json.alert_stock_no) {
                                        p.alert_stock_no = json.alert_stock_no;
                                        row.dataset.alertStockNo = json.alert_stock_no;
                                    }
                                    detailBtn.setAttribute('data-product', JSON.stringify(p));
                                } catch (_) {}
                                detailBtn.style.display = '';
                            }
                            saveBtn.style.display = 'none';
                        })
                        .catch(() => alert('Failed to save.'))
                        .finally(() => { saveBtn.disabled = false; });
                });
            });

            const addPhotoInput = document.getElementById('product_photo');
            const addPhotoFilename = document.getElementById('add-photo-filename');
            if (addPhotoInput && addPhotoFilename) {
                addPhotoInput.addEventListener('change', () => {
                    addPhotoFilename.textContent = addPhotoInput.files[0]?.name || '';
                });
            }

            const detailPhotoInput = document.getElementById('detail-product-photo-input');
            const detailPhotoFilename = document.getElementById('detail-photo-filename');
            if (detailPhotoInput && detailPhotoFilename) {
                detailPhotoInput.addEventListener('change', () => {
                    const file = detailPhotoInput.files[0];
                    if (!file || !currentProductNo) return;
                    detailPhotoFilename.textContent = 'Uploading...';
                    const fd = new FormData();
                    fd.append('photo', file);
                    fetch(`/products/${encodeURIComponent(currentProductNo)}/photos`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '', 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                        .then(r => r.ok ? r.json() : Promise.reject(r))
                        .then(json => {
                            detailPhotoFilename.textContent = '';
                            detailPhotoInput.value = '';
                            carouselPhotos.push({ photo_id: json.photo_id, url: json.url });
                            showCarouselPhoto(carouselPhotos.length - 1);
                        })
                        .catch(() => {
                            detailPhotoFilename.textContent = 'Upload failed.';
                        });
                });
            }

            // --- AJAX product search ---
            const productTableBody   = document.querySelector('#product-list-panel tbody');
            const productTotalChip   = document.querySelector('#product-list-panel .chip');
            const productPager       = document.querySelector('#product-list-panel .pager');
            const searchUrl          = '{{ route('products.index') }}';
            const detailTemplate     = detailForm ? detailForm.dataset.updateTemplate || '' : '';

            function buildRow(p) {
                const photoCell = p.photo_url
                    ? `<img src="${p.photo_url}" alt="Product photo" style="width:46px;height:46px;border-radius:10px;object-fit:cover;">`
                    : `<span class="subtle">No photo</span>`;
                const actionCell = p.can_manage
                    ? `<button type="button" class="btn btn-muted js-product-detail" data-product='${JSON.stringify(p).replace(/'/g,"&#39;")}'>Detail</button>`
                    : `<span class="subtle">Read only</span>`;
                const sellPrice    = parseFloat(p.sell_price) || 0;
                const costPrice    = parseFloat(p.cost_price) || 0;
                const profitPct    = parseFloat(p.profit_percent) || 0;
                return `<tr data-type-id="${escHtml(String(p.product_type_id || ''))}">
                    <td>${photoCell}</td>
                    <td>${escHtml(p.product_name)}</td>
                    <td class="product-type-cell">${escHtml(p.product_type_name || 'N/A')}</td>
                    <td>
                        <div>Sell Price: $${sellPrice.toFixed(2)}</div>
                        <div class="subtle">Cost Price: $${costPrice.toFixed(2)}</div>
                        <div class="subtle">Profit: ${profitPct.toFixed(2)}%</div>
                    </td>
                    <td>${parseInt(p.qty_on_hand) || 0} Pieces</td>
                    <td>${escHtml(p.stock_status || 'N/A')}</td>
                    <td>${actionCell}</td>
                </tr>`;
            }

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function attachDetailListeners() {
                document.querySelectorAll('#product-list-panel .js-product-detail').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!detailModal || !detailForm) return;
                        let data = {};
                        try { data = JSON.parse(button.getAttribute('data-product')); } catch (_) { return; }
                        if (modalTitle) modalTitle.textContent = 'Product Detail';
                        currentProductNo = data.product_no || '';
                        currentRowThumbnail = button.closest('tr')?.querySelector('img') || null;
                        currentAlertRow = null;
                        currentAlertButton = null;
                        if (detailForm) detailForm.action = detailTemplate.replace('__PRODUCT__', encodeURIComponent(currentProductNo));
                        const setValue = (id, value) => { const el = document.getElementById(id); if (el) el.value = value ?? ''; };
                        setValue('detail-product-no', currentProductNo);
                        setValue('detail-product-name', data.product_name || '');
                        setValue('detail-sell-price', data.sell_price ?? '');
                        setValue('detail-cost-price', data.cost_price ?? '');
                        setValue('detail-profit-percent', data.profit_percent ?? '');
                        setValue('detail-qty', data.qty_on_hand ?? '');
                        setValue('detail-unit', data.unit_measure || '');
                        setValue('detail-status', data.stock_status || '');
                        const typeSelect = document.getElementById('detail-product-type');
                        if (typeSelect) typeSelect.value = data.product_type_id ?? '';
                        if (detailCode) detailCode.textContent = currentProductNo ? `#${currentProductNo}` : '#';
                        carouselPhotos = data.photo_url ? [{ photo_id: 0, url: data.photo_url }] : [];
                        carouselIndex = 0;
                        showCarouselPhoto(0);
                        fetch(`/products/${encodeURIComponent(currentProductNo)}/photos`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.ok ? r.json() : null)
                            .then(json => { if (json && Array.isArray(json.photos) && json.photos.length > 0) { carouselPhotos = json.photos; showCarouselPhoto(0); } })
                            .catch(() => {});
                        if (alertView && detailEditFields) {
                            alertView.style.display = 'none';
                            detailEditFields.style.display = '';
                            if (detailForm) detailForm.style.display = '';
                        }
                        detailModal.style.display = '';
                    });
                });
            }

            let ajaxTimer = null;
            function doSearch() {
                const q = document.getElementById('q')?.value || '';
                const type = document.getElementById('type')?.value || '';
                const url = `${searchUrl}?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.ok ? r.json() : Promise.reject(r.status))
                    .then(({ products, total, pagination }) => {
                        if (!productTableBody) return;
                        if (!products || products.length === 0) {
                            productTableBody.innerHTML = '<tr><td colspan="7" class="subtle">No products found.</td></tr>';
                        } else {
                            productTableBody.innerHTML = products.map(buildRow).join('');
                            attachDetailListeners();
                        }
                        if (productTotalChip) productTotalChip.textContent = `${total} total`;
                        if (productPager) productPager.innerHTML = pagination;
                    })
                    .catch((err) => { console.error('Product search failed', err); });
            }

            document.getElementById('q')?.addEventListener('input', () => {
                clearTimeout(ajaxTimer);
                ajaxTimer = setTimeout(doSearch, 350);
            });
            document.getElementById('type')?.addEventListener('change', () => {
                clearTimeout(ajaxTimer);
                doSearch();
            });
            // --- end AJAX product search ---

            // --- AJAX add product ---
            const addProductForm = document.getElementById('add-product-form');
            const addProductBtn  = document.getElementById('add-product-btn');
            const addProductMsg  = document.getElementById('add-product-message');

            if (addProductForm) {
                addProductForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    addProductBtn.disabled = true;
                    addProductBtn.textContent = 'Creating…';
                    if (addProductMsg) { addProductMsg.style.display = 'none'; addProductMsg.textContent = ''; }

                    const fd = new FormData(addProductForm);
                    fetch(addProductForm.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                        .then(r => r.json().then(json => ({ ok: r.ok, json })))
                        .then(({ ok, json }) => {
                            if (!ok) {
                                if (addProductMsg) {
                                    addProductMsg.textContent = json.error || 'Failed to create product.';
                                    addProductMsg.style.display = '';
                                    addProductMsg.style.color = '#dc2626';
                                }
                                return;
                            }
                            // Reset form fields
                            addProductForm.reset();
                            // Set next product code
                            const codeInput = document.getElementById('product_no');
                            if (codeInput && json.next_product_code) codeInput.value = json.next_product_code;
                            // Restore unit measure default
                            const unitInput = document.getElementById('unit_measure');
                            if (unitInput) unitInput.value = 'PCS';
                            // Clear photo filename
                            const addFilename = document.getElementById('add-photo-filename');
                            if (addFilename) addFilename.textContent = '';
                            // Show success
                            if (addProductMsg) {
                                addProductMsg.textContent = json.success || 'Product created.';
                                addProductMsg.style.display = '';
                                addProductMsg.style.color = '#16a34a';
                            }
                            // Refresh the product list
                            doSearch();
                        })
                        .catch(() => {
                            if (addProductMsg) {
                                addProductMsg.textContent = 'An unexpected error occurred.';
                                addProductMsg.style.display = '';
                                addProductMsg.style.color = '#dc2626';
                            }
                        })
                        .finally(() => {
                            addProductBtn.disabled = false;
                            addProductBtn.textContent = 'Create';
                        });
                });
            }
            // --- end AJAX add product ---

            const updateProfit = () => {
                if (!sellPriceInput || !costPriceInput || !profitInput) return;
                const sell = Number(sellPriceInput.value || 0);
                const cost = Number(costPriceInput.value || 0);
                if (sell > 0 && cost > 0) {
                    const profitPercent = ((sell - cost) / cost) * 100;
                    profitInput.value = profitPercent > 0 ? profitPercent.toFixed(2) : '0';
                    return;
                }
                profitInput.value = '0';
            };

            if (sellPriceInput && costPriceInput && profitInput) {
                sellPriceInput.addEventListener('input', updateProfit);
                costPriceInput.addEventListener('input', updateProfit);
                updateProfit();
            }

            // --- Product Types AJAX ---
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const typeFormMsg = document.getElementById('type-form-msg');
            const typesTbody  = document.getElementById('product-types-tbody');

            // --- type save confirmation modal ---
            const typeSaveModal      = document.getElementById('type-save-confirm-modal');
            const typeSaveConfirmBtn = document.getElementById('type-save-confirm-btn');
            const typeSaveCancelBtn  = document.getElementById('type-save-cancel-btn');
            const typeSaveConfirmDesc = document.getElementById('type-save-confirm-desc');
            let pendingSaveCallback  = null;
            let typeSaveModalOpen    = false;

            function confirmTypeSave(callback, description) {
                pendingSaveCallback  = callback;
                typeSaveModalOpen    = true;
                if (typeSaveConfirmDesc) typeSaveConfirmDesc.textContent = description ?? '';
                typeSaveModal.style.display = 'flex';
            }

            function closeTypeSaveModal() {
                typeSaveModalOpen = false;
                typeSaveModal.style.display = 'none';
            }

            typeSaveConfirmBtn?.addEventListener('click', () => {
                closeTypeSaveModal();
                if (pendingSaveCallback) { pendingSaveCallback(); pendingSaveCallback = null; }
            });
            typeSaveCancelBtn?.addEventListener('click', () => {
                closeTypeSaveModal();
                pendingSaveCallback = null;
            });
            typeSaveModal?.addEventListener('click', (e) => {
                if (e.target === typeSaveModal) {
                    closeTypeSaveModal();
                    pendingSaveCallback = null;
                }
            });
            // --- end type save confirmation modal ---

            function updateProductTypeInList(typeId, newName) {
                document.querySelectorAll(`tr[data-type-id="${typeId}"] .product-type-cell`)
                    .forEach(td => { td.textContent = newName; });
            }

            function showTypeMsg(msg, isError) {
                if (!typeFormMsg) return;
                typeFormMsg.textContent = msg;
                typeFormMsg.style.display = '';
                typeFormMsg.style.color = isError ? '#dc2626' : '#16a34a';
                clearTimeout(typeFormMsg._t);
                typeFormMsg._t = setTimeout(() => { typeFormMsg.style.display = 'none'; }, 3500);
            }

            function esc(str) {
                return String(str ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            function buildTypeRow(id, name, remarks) {
                const tr = document.createElement('tr');
                tr.id = 'type-row-' + id;
                tr.dataset.id = id;
                tr.innerHTML = `
                    <td>${id}</td>
                    <td><input type="text" value="${esc(name)}" data-original="${esc(name)}"
                        class="type-name-input" data-id="${id}"
                        data-update-url="/product-types/${id}"></td>
                    <td><input type="text" value="${esc(remarks)}" data-original="${esc(remarks)}"
                        class="type-remarks-input" data-id="${id}" maxlength="255" placeholder="—"></td>
                    <td>
                        <button type="button" class="btn btn-primary type-save-btn" data-id="${id}" style="display:none;">Save</button>
                        <button type="button" class="btn btn-muted type-delete-btn" data-id="${id}" data-delete-url="/product-types/${id}">Delete</button>
                    </td>`;
                bindTypeRow(tr);
                return tr;
            }

            function bindTypeRow(tr) {
                const nameInput   = tr.querySelector('.type-name-input');
                const remarkInput = tr.querySelector('.type-remarks-input');
                const saveBtn     = tr.querySelector('.type-save-btn');
                const deleteBtn   = tr.querySelector('.type-delete-btn');
                if (!nameInput) return;

                function checkChanged() {
                    const changed = nameInput.value !== nameInput.dataset.original
                                 || (remarkInput && remarkInput.value !== remarkInput.dataset.original);
                    saveBtn.style.display   = changed ? '' : 'none';
                    deleteBtn.style.display = changed ? 'none' : '';
                }

                nameInput.addEventListener('input', checkChanged);
                if (remarkInput) remarkInput.addEventListener('input', checkChanged);

                function onBlur() {
                    setTimeout(() => {
                        if (typeSaveModalOpen) return; // modal is handling it — don't revert
                        const active = document.activeElement;
                        if (active !== nameInput && active !== remarkInput && active !== saveBtn) {
                            if (saveBtn.style.display !== 'none') {
                                nameInput.value  = nameInput.dataset.original;
                                if (remarkInput) remarkInput.value = remarkInput.dataset.original;
                                saveBtn.style.display   = 'none';
                                deleteBtn.style.display = '';
                            }
                        }
                    }, 150);
                }

                nameInput.addEventListener('blur', onBlur);
                if (remarkInput) remarkInput.addEventListener('blur', onBlur);
                saveBtn.addEventListener('mousedown', (e) => e.preventDefault());

                saveBtn.addEventListener('click', () => {
                    const newName    = nameInput.value.trim();
                    const newRemarks = remarkInput ? remarkInput.value.trim() : '';
                    if (!newName) { nameInput.focus(); return; }

                    confirmTypeSave(() => {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving…';



                    const fd = new FormData();
                    fd.append('_method', 'PATCH');
                    fd.append('_token', csrfToken);
                    fd.append('type_name', newName);
                    fd.append('remarks', newRemarks);

                    fetch(nameInput.dataset.updateUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            nameInput.value            = newName;
                            nameInput.dataset.original = newName;
                            if (remarkInput) { remarkInput.value = newRemarks; remarkInput.dataset.original = newRemarks; }
                            saveBtn.style.display   = 'none';
                            deleteBtn.style.display = '';
                            updateProductTypeInList(nameInput.dataset.id, newName);
                            showTypeMsg('Updated successfully.', false);
                        } else {
                            showTypeMsg(data.error || 'Failed to update.', true);
                        }
                    })
                    .catch(() => showTypeMsg('An error occurred.', true))
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save';
                    });
                    }, 'Every product that uses this data will also change.'); // end confirmTypeSave
                });

                deleteBtn.addEventListener('click', () => {
                    deleteBtn.disabled = true;
                    deleteBtn.textContent = 'Checking…';

                    fetch(deleteBtn.dataset.deleteUrl.replace(/\/(\d+)$/, '/$1/check-usage'), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                    .then(r => r.json())
                    .then(check => {
                        if (check.in_use) {
                            showTypeMsg('Cannot delete because this data is used by another product.', true);
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = 'Delete';
                            return;
                        }

                        confirmTypeSave(() => {
                            deleteBtn.disabled = true;
                            deleteBtn.textContent = 'Deleting…';


                            const fd = new FormData();
                            fd.append('_method', 'DELETE');
                            fd.append('_token', csrfToken);

                            fetch(deleteBtn.dataset.deleteUrl, {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                body: fd,
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    tr.style.transition = 'opacity 0.25s';
                                    tr.style.opacity = '0';
                                    setTimeout(() => {
                                        tr.remove();
                                        if (!typesTbody.querySelector('tr[data-id]')) {
                                            const emptyTr = document.createElement('tr');
                                            emptyTr.id = 'types-empty-row';
                                            emptyTr.innerHTML = '<td colspan="4" class="subtle">No product types found.</td>';
                                            typesTbody.appendChild(emptyTr);
                                        }
                                    }, 260);
                                    showTypeMsg('Deleted successfully.', false);
                                } else {
                                    showTypeMsg(data.error || 'Failed to delete.', true);
                                    deleteBtn.disabled = false;
                                    deleteBtn.textContent = 'Delete';
                                }
                            })
                            .catch(() => {
                                showTypeMsg('An error occurred.', true);
                                deleteBtn.disabled = false;
                                deleteBtn.textContent = 'Delete';
                            });
                        }, 'Are you sure you want to delete this type?');

                        // if user cancels the modal, re-enable the button
                        typeSaveCancelBtn?.addEventListener('click', function onCancel() {
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = 'Delete';
                            typeSaveCancelBtn.removeEventListener('click', onCancel);
                        }, { once: true });
                    })
                    .catch(() => {
                        showTypeMsg('An error occurred.', true);
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = 'Delete';
                    });
                });
            }

            // Bind existing rows
            document.querySelectorAll('#product-types-tbody tr[data-id]').forEach(tr => bindTypeRow(tr));

            // Add Type
            const addTypeBtn      = document.getElementById('add-type-btn');
            const addTypeInput    = document.getElementById('type_name_input');
            const addRemarksInput = document.getElementById('type_remarks_input');
            if (addTypeBtn && addTypeInput) {
                addTypeBtn.addEventListener('click', () => {
                    const name    = addTypeInput.value.trim();
                    const remarks = addRemarksInput ? addRemarksInput.value.trim() : '';
                    if (!name) { addTypeInput.focus(); return; }
                    addTypeBtn.disabled = true;
                    addTypeBtn.textContent = 'Adding…';

                    const fd = new FormData();
                    fd.append('_token', csrfToken);
                    fd.append('type_name', name);
                    fd.append('remarks', remarks);

                    fetch('{{ route('product-types.create') }}', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const emptyRow = document.getElementById('types-empty-row');
                            if (emptyRow) emptyRow.remove();
                            const tr = buildTypeRow(data.id, data.name, data.remarks ?? '');
                            tr.style.opacity = '0';
                            tr.style.transition = 'opacity 0.25s';
                            typesTbody.appendChild(tr);
                            requestAnimationFrame(() => { tr.style.opacity = '1'; });
                            addTypeInput.value = '';
                            if (addRemarksInput) addRemarksInput.value = '';
                            showTypeMsg('Type added successfully.', false);
                        } else {
                            showTypeMsg(data.error || 'Failed to add type.', true);
                        }
                    })
                    .catch(() => showTypeMsg('An error occurred.', true))
                    .finally(() => {
                        addTypeBtn.disabled = false;
                        addTypeBtn.textContent = 'Add Type';
                    });
                });

                addTypeInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') addTypeBtn.click();
                });
            }
            // --- end Product Types AJAX ---
        });
    </script>
@endsection
