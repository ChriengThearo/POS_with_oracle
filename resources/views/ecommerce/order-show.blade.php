@extends('layouts.ecommerce')

@section('title', 'Order #' . ($order['header']->invoice_no ?? ''))

@section('content')
    @php
        $header = $order['header'];
        $canShop = \App\Support\StaffAuth::can('shop.read');
        $canManageOrders = \App\Support\StaffAuth::can('orders.manage');
        $canManageClientDebts = \App\Support\StaffAuth::can('client-depts.manage');
        $invoiceStatus = (string) ($header->invoice_status ?? '');
        $currentDebt = round(max(0, (float) ($debtAmount ?? 0)), 2);
        $hasDebt = $currentDebt > 0;
        $showRepayAction = $canManageClientDebts && ($hasDebt || $invoiceStatus === 'In Debt');
        $paymentCurrencies = collect($paymentCurrencies ?? [])->values();
        $defaultPaymentCurrency = trim((string) ($defaultPaymentCurrency ?? 'USD'));
        if ($defaultPaymentCurrency === '') {
            $defaultPaymentCurrency = 'USD';
        }
        $initialPaymentCurrency = old('payment_currency', $defaultPaymentCurrency);
    @endphp

    <section class="card">
        <div class="actions" style="justify-content: space-between;">
            <div>
                <h1 class="headline">Invoice #{{ $header->invoice_no }}</h1>
                <p class="subtle">
                    Date: {{ \Illuminate\Support\Carbon::parse($header->invoice_date)->format('Y-m-d H:i') }}
                    | Status: <span class="chip">{{ $header->invoice_status ?? 'N/A' }}</span>
                </p>
            </div>
            <div class="actions" style="align-items: flex-start;">
                <div style="display: grid; gap: 8px;">
                    <a href="{{ $backUrl ?? route('store.orders') }}" class="btn btn-muted">{{ $backLabel ?? 'Back To Orders' }}</a>
                    @if($showRepayAction)
                        <button type="button" class="btn btn-primary" id="open-repay-modal">Repay</button>
                    @endif
                </div>
                @if(($header->invoice_status ?? '') === 'In Process' && $canManageOrders)
                    <form method="POST" action="{{ route('store.orders.complete', ['invoiceNo' => (int) $header->invoice_no]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary">Complete</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if($showRepayAction)
        <form method="POST" action="{{ route('store.orders.repay', ['invoiceNo' => (int) $header->invoice_no]) }}" id="repay-order-form" style="display: none;">
            @csrf
            <input type="hidden" name="payment_amount" id="repay-payment-amount" value="{{ number_format($currentDebt, 2, '.', '') }}">
            <input type="hidden" name="recieve_amount" id="repay-recieve-amount" value="">
            <input type="hidden" name="payment_currency" id="repay-payment-currency-input" value="{{ $initialPaymentCurrency }}">
        </form>

        <div
            id="repay-modal"
            style="position: fixed; inset: 0; background: rgba(10, 14, 20, 0.55); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 16px;"
            aria-hidden="true"
        >
            <div class="card" role="dialog" aria-modal="true" aria-labelledby="repay-modal-title" style="width: min(460px, 100%); margin: 0;">
                <h3 id="repay-modal-title" style="margin-top: 0;">Repay Invoice</h3>
                <div style="display: grid; gap: 12px; margin-top: 12px;">
                    <div class="actions" style="justify-content: space-between; align-items: baseline;">
                        <strong>Amount:</strong>
                        <span id="repay-modal-amount" style="font-weight: 700;">0.00 USD</span>
                    </div>
                    <div>
                        <label for="repay-payment-currency">Currency Type</label>
                        <select id="repay-payment-currency">
                            @forelse($paymentCurrencies as $currency)
                                @php
                                    $currencyValue = (string) ($currency['value'] ?? '');
                                    $currencyLabel = (string) ($currency['label'] ?? $currencyValue);
                                    $currencyRate = is_numeric($currency['rate_to_usd'] ?? null) ? (float) $currency['rate_to_usd'] : 1;
                                    $currencyCode = trim((string) ($currency['code'] ?? 'USD'));
                                @endphp
                                @if($currencyValue !== '')
                                    <option value="{{ $currencyValue }}" data-rate="{{ number_format($currencyRate, 6, '.', '') }}" data-code="{{ $currencyCode !== '' ? $currencyCode : 'USD' }}" @selected((string) $initialPaymentCurrency === $currencyValue)>{{ $currencyLabel }}</option>
                                @endif
                            @empty
                                <option value="{{ $defaultPaymentCurrency }}" data-rate="1.000000" data-code="USD" selected>{{ $defaultPaymentCurrency }}</option>
                            @endforelse
                        </select>
                        <div class="subtle" id="repay-payment-rate-note" style="margin-top: 6px;">EXCHANGE_RATE_TO_USD: 1 USD = 1.00 USD</div>
                    </div>
                    <div>
                        <label for="repay-recieve-input">Recieve Amount</label>
                        <input id="repay-recieve-input" type="number" min="0" step="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                    <div class="actions" id="repay-debt-row" style="justify-content: space-between; align-items: baseline;">
                        <strong>Debt:</strong>
                        <span id="repay-modal-debt" style="font-weight: 700;">0.00 USD</span>
                    </div>
                    <div class="actions" id="repay-change-row" style="justify-content: space-between; align-items: baseline; display: none;">
                        <strong>Change:</strong>
                        <span id="repay-modal-change" style="font-weight: 700;">0.00 USD</span>
                    </div>
                </div>
                <div class="actions" style="justify-content: flex-end; gap: 10px; margin-top: 18px;">
                    <button type="button" class="btn btn-muted" id="repay-cancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="repay-submit">Submit</button>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const repayAmount = Number(@json($currentDebt));
                const openButton = document.getElementById('open-repay-modal');
                const modal = document.getElementById('repay-modal');
                const amountText = document.getElementById('repay-modal-amount');
                const debtText = document.getElementById('repay-modal-debt');
                const changeText = document.getElementById('repay-modal-change');
                const debtRow = document.getElementById('repay-debt-row');
                const changeRow = document.getElementById('repay-change-row');
                const receiveInput = document.getElementById('repay-recieve-input');
                const cancelButton = document.getElementById('repay-cancel');
                const submitButton = document.getElementById('repay-submit');
                const form = document.getElementById('repay-order-form');
                const paymentAmountInput = document.getElementById('repay-payment-amount');
                const paymentReceiveInput = document.getElementById('repay-recieve-amount');
                const paymentCurrencySelect = document.getElementById('repay-payment-currency');
                const paymentCurrencyInput = document.getElementById('repay-payment-currency-input');
                const paymentRateNote = document.getElementById('repay-payment-rate-note');

                if (!openButton || !modal || !amountText || !debtText || !receiveInput || !submitButton || !form || !paymentAmountInput || !paymentReceiveInput || !paymentCurrencySelect || !paymentCurrencyInput) {
                    return;
                }

                const roundMoney = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
                const selectedCurrencyRate = () => {
                    const selected = paymentCurrencySelect.options[paymentCurrencySelect.selectedIndex];
                    const raw = Number(selected?.dataset.rate ?? 1);
                    return Number.isFinite(raw) && raw > 0 ? raw : 1;
                };
                const selectedCurrencyCode = () => {
                    const selected = paymentCurrencySelect.options[paymentCurrencySelect.selectedIndex];
                    const code = String(selected?.dataset.code ?? '').trim();
                    return code !== '' ? code : 'USD';
                };
                const formatSelectedCurrency = (value) => {
                    return `${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${selectedCurrencyCode()}`;
                };
                const updateRateNote = () => {
                    if (!paymentRateNote) {
                        return;
                    }

                    const rate = selectedCurrencyRate();
                    paymentRateNote.textContent = `EXCHANGE_RATE_TO_USD: 1 USD = ${rate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${selectedCurrencyCode()}`;
                };

                const updatePreview = () => {
                    const received = Math.max(0, Number(receiveInput.value || 0));
                    const repayAmountLocal = roundMoney(repayAmount * selectedCurrencyRate());
                    const debt = roundMoney(Math.max(0, repayAmountLocal - received));
                    const change = roundMoney(Math.max(0, received - repayAmountLocal));

                    debtText.textContent = formatSelectedCurrency(debt);
                    if (debtRow) {
                        debtRow.style.display = debt > 0 ? '' : 'none';
                    }
                    if (changeText) {
                        changeText.textContent = formatSelectedCurrency(change);
                    }
                    if (changeRow) {
                        changeRow.style.display = change > 0 ? '' : 'none';
                    }
                };

                const openModal = () => {
                    if (paymentCurrencyInput.value) {
                        paymentCurrencySelect.value = paymentCurrencyInput.value;
                    }
                    const repayAmountLocal = roundMoney(repayAmount * selectedCurrencyRate());
                    amountText.textContent = formatSelectedCurrency(repayAmountLocal);
                    updateRateNote();
                    receiveInput.value = '';
                    updatePreview();
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    receiveInput.focus();
                };

                const closeModal = () => {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                };

                openButton.addEventListener('click', openModal);
                receiveInput.addEventListener('input', updatePreview);
                paymentCurrencySelect.addEventListener('change', () => {
                    paymentCurrencyInput.value = String(paymentCurrencySelect.value || '').trim();
                    const repayAmountLocal = roundMoney(repayAmount * selectedCurrencyRate());
                    amountText.textContent = formatSelectedCurrency(repayAmountLocal);
                    updateRateNote();
                    updatePreview();
                });
                cancelButton?.addEventListener('click', closeModal);
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.style.display === 'flex') {
                        closeModal();
                    }
                });

                submitButton.addEventListener('click', () => {
                    const received = Math.max(0, Number(receiveInput.value || 0));
                    paymentAmountInput.value = repayAmount.toFixed(2);
                    paymentReceiveInput.value = received.toFixed(2);
                    paymentCurrencyInput.value = String(paymentCurrencySelect.value || '').trim();
                    form.submit();
                });
            })();
        </script>
    @endif

    <div class="grid grid-2">
        <section class="card">
            <h2 style="margin-top: 0;">Customer</h2>
            <p class="subtle" style="margin-top: 6px;">
                <strong>{{ $header->client_name }}</strong><br>
                Client No: {{ $header->client_no }}<br>
                Phone: {{ $header->phone }}<br>
                City: {{ $header->city ?: 'N/A' }}<br>
                Address: {{ $header->address ?: 'N/A' }}
            </p>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Seller</h2>
            <p class="subtle" style="margin-top: 6px;">
                <strong>{{ $header->employee_name }}</strong><br>
                Employee ID: {{ $header->employee_id }}<br>
                Memo: {{ $header->invoice_memo ?: 'N/A' }}
            </p>
        </section>
    </div>

    <section class="card">
        <h2 style="margin-top: 0;">Line Items</h2>
        <div class="table-wrap" style="margin-top: 10px;">
            <table>
                <thead>
                <tr>
                    <th>Product No</th>
                    <th>Product Name</th>
                    <th style="text-align: right;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">Line Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($order['items'] as $item)
                    <tr>
                        <td>{{ $item->product_no }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td style="text-align: right;">{{ number_format((float) $item->qty) }}</td>
                        <td style="text-align: right;">${{ number_format((float) $item->price, 2) }}</td>
                        <td style="text-align: right;">${{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="grid grid-4">
            <article class="stat">
                <div class="label">Item Count</div>
                <div class="value">{{ number_format((int) $order['item_count']) }}</div>
            </article>
            <article class="stat">
                <div class="label">Subtotal</div>
                <div class="value">${{ number_format((float) $order['subtotal'], 2) }}</div>
            </article>
            <article class="stat">
                <div class="label">Discount Rate</div>
                <div class="value">{{ number_format((float) ($order['discount_rate'] * 100), 2) }}%</div>
            </article>
            <article class="stat">
                <div class="label">Grand Total</div>
                <div class="value">${{ number_format((float) $order['grand_total'], 2) }}</div>
            </article>
        </div>
    </section>
@endsection
