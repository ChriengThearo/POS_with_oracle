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

        <form method="GET" action="{{ route('products.index') }}" class="field-grid" style="margin-top: 14px;">
            <div>
                <label for="q">Search product / code / type</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="e.g. P0001 or Laptop">
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
            <div class="actions" style="align-items: end;">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="{{ route('products.index') }}" class="btn btn-muted">Reset</a>
            </div>
        </form>
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
                <span class="subtle">Use the buttons to switch between product sections.</span>
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
                        <tr>
                            <td>
                                @if($photoUrl !== '')
                                    <img src="{{ $photoUrl }}" alt="Product photo" style="width: 46px; height: 46px; border-radius: 10px; object-fit: cover;">
                                @else
                                    <span class="subtle">No photo</span>
                                @endif
                            </td>
                            <td>{{ $product->product_name }}</td>
                            <td>{{ $product->product_type_name ?? 'N/A' }}</td>
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
                                $detailPayload = [
                                    'alert_stock_no' => (int) $alert->alert_stock_no,
                                    'product_no' => (string) $alert->product_no,
                                    'product_name' => (string) $alert->product_name,
                                    'product_type_id' => (string) $alert->product_type_id,
                                    'sell_price' => (float) $alert->sell_price,
                                    'cost_price' => (float) $alert->cost_price,
                                    'profit_percent' => (float) $profitDisplay,
                                    'lower_qty' => (float) $alert->lower_qty,
                                    'higher_qty' => (float) $alert->higher_qty,
                                    'unit_measure' => (string) $alert->unit_measure,
                                    'stock_status' => (string) ($alert->stock_status ?? ''),
                                    'photo_url' => (string) $photoUrl,
                                ];
                            @endphp
                            <tr>
                                <td>
                                    @if($photoUrl !== '')
                                        <img src="{{ $photoUrl }}" alt="Product photo" style="width: 46px; height: 46px; border-radius: 10px; object-fit: cover;">
                                    @else
                                        <span class="subtle">No photo</span>
                                    @endif
                                </td>
                                <td>{{ $alert->product_name }}</td>
                                <td style="text-align: right;">{{ number_format((float) $alert->lower_qty) }}</td>
                                <td style="text-align: right;">{{ number_format((float) $alert->higher_qty) }}</td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-muted js-product-detail"
                                        data-mode="alert"
                                        data-product='@json($detailPayload)'
                                    >
                                        Detail
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
            <form method="POST" action="{{ route('products.store') }}" class="field-grid" style="margin-top: 12px;" enctype="multipart/form-data">
                @csrf
                <div>
                    <label for="product_no">Product Code</label>
                    <input id="product_no" name="product_no" type="text" required>
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
                    <input id="unit_measure" name="unit_measure" type="text" required>
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
                <input id="qty_on_hand" name="qty_on_hand" type="hidden" value="0">
                <input id="status" name="status" type="hidden" value="">
                <div class="actions" style="align-items: end;">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </section>
        @endif

        <section class="card" id="product-types-panel" style="display: none;">
            <div style="margin-top: 18px;">
                <h3 style="margin-top: 0;">Product Types</h3>
                <p class="subtle">Manage product type names.</p>
            </div>

            @if($canManageTypes)
                <form method="POST" action="{{ route('product-types.create') }}" class="field-grid" style="margin-top: 12px;">
                    @csrf
                    <div>
                        <label for="type_name">Type name</label>
                        <input id="type_name" name="type_name" type="text" required>
                    </div>
                    <div class="actions" style="align-items: end;">
                        <button type="submit" class="btn btn-primary">Add Type</button>
                    </div>
                </form>
            @endif

            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($types as $type)
                        <tr>
                            <td>{{ $type->id }}</td>
                            <td>
                                @if($canManageTypes)
                                    <input type="text" form="type-{{ $type->id }}" name="type_name" value="{{ $type->name }}">
                                @else
                                    {{ $type->name }}
                                @endif
                            </td>
                            <td>
                                @if($canManageTypes)
                                    <form id="type-{{ $type->id }}" method="POST" action="{{ route('product-types.update', ['typeId' => (int) $type->id]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-muted">Save</button>
                                    </form>
                                @else
                                    <span class="subtle">Read only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="subtle">No product types found.</td>
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
                    style="margin-top: 12px;"
                >
                    @csrf
                    @method('PATCH')
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
                        <input id="detail-qty" name="qty_on_hand" type="number" min="0" readonly>
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
                                const alertTemplate = alertForm.dataset.updateTemplate || '';
                                const alertStockNo = data.alert_stock_no ?? '';
                                alertForm.action = alertTemplate.replace('__ALERT__', encodeURIComponent(alertStockNo));
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

            if (detailModal) {
                detailModal.addEventListener('click', (event) => {
                    if (event.target === detailModal) {
                        detailModal.style.display = 'none';
                    }
                });
            }

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
        });
    </script>
@endsection
