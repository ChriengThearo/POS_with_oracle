@extends('layouts.ecommerce')

@section('title', 'Shop Catalog')

@section('content')
    @php
        $canManageCart = \App\Support\StaffAuth::can('checkout.process');
    @endphp
    <section class="card">
        <div class="actions" style="justify-content: space-between;">
            <div>
                <h1 class="headline">Product Catalog</h1>
                <p class="subtle">Browse and filter inventory directly from <code>PRODUCTS</code> and <code>PRODUCT_TYPE</code>.</p>
            </div>
            @if($canManageCart)
                <a href="{{ route('store.cart') }}" class="btn btn-primary">Open Cart</a>
            @endif
        </div>

        <form method="GET" action="{{ route('store.catalog') }}" class="field-grid" style="margin-top: 12px;">
            <div>
                <label for="q">Search name or code</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="e.g. P0001, iPhone">
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
            <div>
                <label for="stock">Stock view</label>
                <select id="stock" name="stock">
                    <option value="">Any stock state</option>
                    <option value="in_stock" @selected($stock === 'in_stock')>In stock only</option>
                    <option value="understock" @selected($stock === 'understock')>Understock only</option>
                    <option value="overstock" @selected($stock === 'overstock')>Overstock only</option>
                </select>
            </div>
            <div class="actions" style="align-items: end;">
                <button class="btn btn-primary" type="submit">Apply Filters</button>
                <a href="{{ route('store.catalog') }}" class="btn btn-muted">Reset</a>
            </div>
        </form>
    </section>

    <section class="grid grid-3">
        @forelse($products as $product)
            @php
                $qty = (int) $product->qty_on_hand;
                $status = strtoupper((string) ($product->stock_status ?? ''));
                $isSellable = $qty > 0;
                $profitDisplay = (float) $product->profit_percent;
                if ($profitDisplay > 0 && $profitDisplay <= 1) {
                    $profitDisplay *= 100;
                }
            @endphp
            <article class="card">
                <div class="actions" style="justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div class="chip">{{ $product->product_no }}</div>
                        <h3 style="margin: 10px 0 6px;">{{ $product->product_name }}</h3>
                        <p class="subtle" style="margin: 0;">{{ $product->product_type_name ?? 'Uncategorized' }}</p>
                    </div>
                    <span class="chip">{{ $status !== '' ? $status : 'N/A' }}</span>
                </div>

                <div class="grid grid-2" style="margin-top: 12px;">
                    <div class="stat">
                        <div class="label">Sell Price</div>
                        <div class="value">${{ number_format((float) $product->sell_price, 2) }}</div>
                    </div>
                    <div class="stat">
                        <div class="label">Quantity</div>
                        <div class="value">{{ number_format($qty) }}</div>
                    </div>
                </div>

                <div class="subtle" style="margin-top: 10px;">
                    Cost: ${{ number_format((float) $product->cost_price, 2) }}<br>
                    Profit: {{ number_format($profitDisplay, 2) }}%<br>
                    Unit: {{ $product->unit_measure }}
                </div>

                @if($canManageCart)
                    <form method="POST" action="{{ route('store.cart.add') }}" class="actions" style="margin-top: 14px;">
                        @csrf
                        <input type="hidden" name="product_no" value="{{ $product->product_no }}">
                        <input
                            type="number"
                            name="qty"
                            min="1"
                            max="{{ max(1, $qty) }}"
                            value="1"
                            style="max-width: 95px;"
                            @disabled(!$isSellable)
                        >
                        <button type="submit" class="btn btn-primary" @disabled(!$isSellable)>
                            {{ $isSellable ? 'Add To Cart' : 'Out of Stock' }}
                        </button>
                    </form>
                @endif
            </article>
        @empty
            <section class="card">
                <h3 style="margin-top: 0;">No products found</h3>
                <p class="subtle">Try different filters or reset your search.</p>
            </section>
        @endforelse
    </section>

    @if ($products instanceof \Illuminate\Contracts\Pagination\Paginator || $products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        <section class="card pager">
            {{ $products->links('pagination.orbit') }}
        </section>
    @endif
@endsection
