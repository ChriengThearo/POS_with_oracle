@extends('layouts.ecommerce')

@section('title', 'Invoices')

@section('content')
    @php
        $header = $order['header'] ?? null;
        $items = $order['items'] ?? collect();
        $subtotal = (float) ($order['subtotal'] ?? 0);
        $discountRate = (float) ($order['discount_rate'] ?? 0);
        $discountAmount = (float) ($order['discount_amount'] ?? 0);
        $grandTotal = (float) ($order['grand_total'] ?? 0);
        $isNew = $isNew ?? false;
        $staffUser = \App\Support\StaffAuth::user();
        $isCompleted = ! $isNew && (($header->invoice_status ?? '') === 'Completed');
        $paymentCurrencies = collect($paymentCurrencies ?? [])->values();
        $defaultPaymentCurrency = trim((string) ($defaultPaymentCurrency ?? 'USD'));
        if ($defaultPaymentCurrency === '') {
            $defaultPaymentCurrency = 'USD';
        }
        $initialPaymentCurrency = old('payment_currency', $defaultPaymentCurrency);
    @endphp

    <section class="card" style="padding: 0; overflow: hidden;">
        <div style="display: flex; align-items: center; gap: 16px; padding: 18px; background: #5d7fa8; color: #fff;">
            <div style="width: 64px; height: 64px; border-radius: 16px; background: #ffffff; color: #5d7fa8; display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: 'Space Grotesk', sans-serif;">
                INV
            </div>
            <div>
                <h1 class="headline" style="color: #fff;">Create Invoices</h1>
                <div style="opacity: .9; font-size: .95rem;">Main invoice form with invoice detail subform.</div>
            </div>
        </div>
        <div style="padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <form method="GET" action="{{ route('invoices.index') }}" class="actions">
                <label for="invoice_no" style="margin: 0; font-size: .85rem;">Search by Invoice No:</label>
                <select id="invoice_no" name="invoice_no" onchange="this.form.submit()">
                    <option value="">Select invoice</option>
                    @foreach($invoiceNos as $invoiceRow)
                        <option value="{{ $invoiceRow->invoice_no }}" @selected((int) $selectedInvoiceNo === (int) $invoiceRow->invoice_no)>
                            #{{ $invoiceRow->invoice_no }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-muted">Load</button>
            </form>
            @if($header)
                <span class="chip">Invoice #{{ $header->invoice_no }}</span>
            @endif
        </div>
        @if(\App\Support\StaffAuth::can('invoices.manage'))
            <div class="actions" style="justify-content: flex-end; padding: 0 16px 16px;">
                <a href="{{ route('invoices.index', ['new' => 1]) }}" class="btn btn-primary">New Invoice</a>
            </div>
        @endif
    </section>

    @if(! $header && ! $isNew)
        <section class="card">
            <div class="subtle">No invoice selected. Choose an invoice number to view the form.</div>
        </section>
    @else
        <section class="card" id="invoice-main-section">
            <div class="actions" style="justify-content: space-between;">
                <h2 style="margin-top: 0;">Invoice (Main Form)</h2>
                <span class="chip">{{ $isNew ? 'In Process' : ($header->invoice_status ?? 'N/A') }}</span>
            </div>

            <div class="grid grid-2" style="margin-top: 12px;">
                <div style="display: grid; gap: 10px;">
                    <div>
                        <label for="client_name">Sold To</label>
                        @if($isNew)
                            <select id="new-client-no" name="client_no" form="new-invoice-form" required>
                                <option value="">Select client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->client_no }}"
                                            data-discount="{{ $client->discount }}"
                                            data-address="{{ $client->address }}"
                                            data-city="{{ $client->city }}"
                                            data-phone="{{ $client->phone }}">
                                        {{ $client->client_name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input id="client_name" type="text" value="{{ $header->client_name }}" readonly>
                        @endif
                    </div>
                    <div>
                        <label for="address">Address</label>
                        <input id="address" type="text" value="{{ $isNew ? '' : ($header->address ?: 'N/A') }}" readonly>
                    </div>
                    <div>
                        <label for="city">City</label>
                        <input id="city" type="text" value="{{ $isNew ? '' : ($header->city ?: 'N/A') }}" readonly>
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input id="phone" type="text" value="{{ $isNew ? '' : ($header->phone ?: 'N/A') }}" readonly>
                    </div>
                    <div>
                        <label for="discount">Discount</label>
                        <input id="discount" type="text" value="{{ $isNew ? '0.00%' : number_format($discountRate * 100, 2) . '%' }}" readonly>
                    </div>
                </div>

                <div style="display: grid; gap: 10px;">
                    <div>
                        <label for="invoice_no_field">Invoice No</label>
                        <input id="invoice_no_field" type="text" value="{{ $isNew ? 'Auto' : $header->invoice_no }}" readonly>
                    </div>
                    <div>
                        <label for="invoice_date">Invoice Date</label>
                        <input id="invoice_date" type="text" value="{{ $isNew ? now()->format('Y-m-d H:i') : \Illuminate\Support\Carbon::parse($header->invoice_date)->format('Y-m-d H:i') }}" readonly>
                    </div>
                    <div>
                        <label for="seller">Seller</label>
                        <input id="seller" type="text" value="{{ $isNew ? ($staffUser['employee_name'] ?? 'N/A') : $header->employee_name }}" readonly>
                    </div>
                    <div>
                        <label for="status">Invoice Status</label>
                        <input id="status" type="text" value="{{ $isNew ? 'In Process' : ($header->invoice_status ?? 'N/A') }}" readonly>
                    </div>
                    <div>
                        <label for="memo">Invoice Memo</label>
                        @if($isNew)
                            <input id="memo" name="invoice_memo" form="new-invoice-form" type="text" value="">
                        @else
                            <input id="memo" type="text" value="{{ $header->invoice_memo ?: 'N/A' }}" readonly>
                        @endif
                    </div>
                </div>
            </div>

            @if($isNew)
                <form id="new-invoice-form" method="POST" action="{{ route('invoices.store') }}" class="actions" style="margin-top: 12px;">
                    @csrf
                    <input type="hidden" name="invoice_status" value="In Process">
                </form>
            @endif
        </section>

        <section class="card" id="invoice-details-section">
            <div class="actions" style="justify-content: space-between;">
                <h2 style="margin-top: 0;">Invoice Details (Sub Form)</h2>
                <span class="chip">{{ $isNew ? 0 : number_format((int) ($order['item_count'] ?? 0)) }} items</span>
            </div>

            @if(! $isNew)
                <form method="POST" action="{{ route('invoices.items.store', ['invoiceNo' => $header->invoice_no]) }}" id="invoice-items-form">
                    @csrf
            @endif

            <select id="invoice-item-product-template" style="display: none;">
                <option value="">Select product</option>
                @foreach($products as $product)
                    <option value="{{ $product->product_no }}">{{ $product->product_name }}</option>
                @endforeach
            </select>

            <div class="table-wrap" style="margin-top: 12px;">
                <table>
                    <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Product Name</th>
                        <th style="text-align: right;">Qty</th>
                        <th style="text-align: right;">Price</th>
                        <th style="text-align: right;">Amount</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if(! $isNew)
                        @forelse($items as $item)
                            @php
                                $deleteFormId = 'delete-invoice-item-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $item->product_no);
                            @endphp
                            <tr>
                                <td class="subtle" style="text-align: center;">--</td>
                                <td>{{ $item->product_name }}</td>
                                <td style="text-align: right;">{{ number_format((float) $item->qty) }}</td>
                                <td style="text-align: right;">${{ number_format((float) $item->price, 2) }}</td>
                                <td style="text-align: right;">${{ number_format((float) $item->line_total, 2) }}</td>
                                <td style="text-align: center;">
                                <button type="submit" class="btn btn-danger js-invoice-action" title="Delete item" aria-label="Delete item" form="{{ $deleteFormId }}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="display: inline-block; vertical-align: middle;">
                                        <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 7h2v8h-2v-8zm4 0h2v8h-2v-8zM7 8h10l-1 13H8L7 8z"/>
                                    </svg>
                                </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="subtle" id="invoice-items-empty">No invoice details found.</td>
                            </tr>
                        @endforelse
                    @else
                        <tr>
                            <td colspan="6" class="subtle" id="invoice-items-empty">No items added yet.</td>
                        </tr>
                    @endif
                    </tbody>
                    <tbody id="invoice-items-new"></tbody>
                </table>
            </div>

            <div class="actions" style="justify-content: flex-end; margin-top: 12px;">
                <button type="button" class="btn btn-muted js-invoice-action" id="add-invoice-item" @if($isNew) disabled @endif>Add Item</button>
                <button type="submit" class="btn btn-primary js-invoice-action" id="save-invoice-items" disabled @if($isNew) form="new-invoice-form" @endif>Save Items</button>
            </div>

            @if(! $isNew)
                </form>

                @foreach($items as $item)
                    @php
                        $deleteFormId = 'delete-invoice-item-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $item->product_no);
                    @endphp
                    <form
                        id="{{ $deleteFormId }}"
                        method="POST"
                        action="{{ route('invoices.items.destroy', ['invoiceNo' => $header->invoice_no, 'productNo' => $item->product_no]) }}"
                        style="display: none;"
                    >
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        </section>

        <input type="hidden" id="invoice-payment-amount-input" name="payment_amount" form="{{ $isNew ? 'new-invoice-form' : 'invoice-items-form' }}" value="">
        <input type="hidden" id="invoice-recieve-amount-input" name="recieve_amount" form="{{ $isNew ? 'new-invoice-form' : 'invoice-items-form' }}" value="">
        <input type="hidden" id="invoice-payment-currency-input" name="payment_currency" form="{{ $isNew ? 'new-invoice-form' : 'invoice-items-form' }}" value="{{ $initialPaymentCurrency }}">

        <section class="card">
            <div class="grid grid-3">
                <article class="stat">
                    <div class="label">Sub Total</div>
                    <div class="value" id="subtotal-value">${{ number_format($subtotal, 2) }}</div>
                </article>
                <article class="stat">
                    <div class="label">Discount Amount</div>
                    <div class="value" id="discount-value">-${{ number_format($discountAmount, 2) }}</div>
                </article>
                <article class="stat">
                    <div class="label">Grand Total</div>
                    <div class="value" id="grandtotal-value">${{ number_format($grandTotal, 2) }}</div>
                </article>
            </div>
        </section>

        <div
            id="invoice-payment-modal"
            style="position: fixed; inset: 0; background: rgba(10, 14, 20, 0.55); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 16px;"
            aria-hidden="true"
        >
            <div class="card" role="dialog" aria-modal="true" aria-labelledby="invoice-payment-title" style="width: min(460px, 100%); margin: 0;">
                <h3 id="invoice-payment-title" style="margin-top: 0;">Payment Summary</h3>
                <div style="display: grid; gap: 12px; margin-top: 12px;">
                    <div class="actions" style="justify-content: space-between; align-items: baseline;">
                        <strong>Amount:</strong>
                        <span id="invoice-payment-amount" style="font-weight: 700;">$0.00</span>
                    </div>
                    <div>
                        <label for="invoice-payment-currency">Currency Type</label>
                        <select id="invoice-payment-currency">
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
                        <div class="subtle" id="invoice-payment-rate-note" style="margin-top: 6px;">EXCHANGE_RATE_TO_USD: 1 USD = 1.00 {{ $defaultPaymentCurrency }}</div>
                    </div>
                    <div>
                        <label for="invoice-receive-amount">Recieve Amount</label>
                        <input id="invoice-receive-amount" type="number" min="0" step="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                    <div class="actions" id="invoice-payment-debt-row" style="justify-content: space-between; align-items: baseline;">
                        <strong>Debt:</strong>
                        <span id="invoice-payment-debt" style="font-weight: 700;">$0.00</span>
                    </div>
                    <div class="actions" id="invoice-payment-change-row" style="justify-content: space-between; align-items: baseline; display: none;">
                        <strong>Change:</strong>
                        <span id="invoice-payment-change" style="font-weight: 700;">$0.00</span>
                    </div>
                </div>
                <div class="actions" style="justify-content: flex-end; gap: 10px; margin-top: 18px;">
                    <button type="button" class="btn btn-muted" id="invoice-payment-cancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="invoice-payment-submit">Submit</button>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const invoiceCompleted = @json($isCompleted);
                const completedAlert = "Can't Update. Invoice have Completed";
                const clientSelect = document.getElementById('new-client-no');
                const discountField = document.getElementById('discount');
                const addressField = document.getElementById('address');
                const cityField = document.getElementById('city');
                const phoneField = document.getElementById('phone');
                const mainSection = document.getElementById('invoice-main-section');
                const detailsSection = document.getElementById('invoice-details-section');

                const blockEdits = (section) => {
                    if (!section) return;
                    section.addEventListener('submit', (event) => {
                        event.preventDefault();
                        alert(completedAlert);
                    });
                    section.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) return;
                        if (target.closest('button, input, select, textarea, a')) {
                            event.preventDefault();
                            alert(completedAlert);
                        }
                    });
                };

                if (invoiceCompleted) {
                    blockEdits(mainSection);
                    blockEdits(detailsSection);
                    document.querySelectorAll('.js-invoice-action').forEach((button) => {
                        if (button instanceof HTMLButtonElement) {
                            button.disabled = true;
                            button.setAttribute('aria-disabled', 'true');
                        }
                    });
                    return;
                }

                const updateDiscount = () => {
                    if (!clientSelect || !discountField) return;
                    const selected = clientSelect.options[clientSelect.selectedIndex];
                    const raw = Number(selected?.dataset?.discount || 0);
                    let percent = 0;
                    if (raw > 0) {
                        if (raw < 1) {
                            percent = raw * 100;
                        } else if (raw <= 10) {
                            percent = raw * 10;
                        } else {
                            percent = raw;
                        }
                    }
                    discountField.value = `${percent.toFixed(2)}%`;
                };

                if (clientSelect) {
                    clientSelect.addEventListener('change', () => {
                        updateDiscount();
                        const selected = clientSelect.options[clientSelect.selectedIndex];
                        if (addressField) addressField.value = selected?.dataset?.address || '';
                        if (cityField) cityField.value = selected?.dataset?.city || '';
                        if (phoneField) phoneField.value = selected?.dataset?.phone || '';
                    });
                    updateDiscount();
                }

                const isNewInvoice = @json($isNew);
                const activeFormId = isNewInvoice ? 'new-invoice-form' : 'invoice-items-form';
                const activeForm = activeFormId ? document.getElementById(activeFormId) : null;
                const addButton = document.getElementById('add-invoice-item');
                const rowsContainer = document.getElementById('invoice-items-new');
                const saveButton = document.getElementById('save-invoice-items');
                const emptyRow = document.getElementById('invoice-items-empty');
                const productTemplate = document.getElementById('invoice-item-product-template');
                const priceEndpoint = @json(route('products.price'));
                const subtotalValue = document.getElementById('subtotal-value');
                const discountValue = document.getElementById('discount-value');
                const grandTotalValue = document.getElementById('grandtotal-value');
                const paymentModal = document.getElementById('invoice-payment-modal');
                const paymentAmountText = document.getElementById('invoice-payment-amount');
                const paymentDebtText = document.getElementById('invoice-payment-debt');
                const paymentDebtRow = document.getElementById('invoice-payment-debt-row');
                const paymentChangeText = document.getElementById('invoice-payment-change');
                const paymentChangeRow = document.getElementById('invoice-payment-change-row');
                const receiveAmountInput = document.getElementById('invoice-receive-amount');
                const paymentCancelButton = document.getElementById('invoice-payment-cancel');
                const paymentSubmitButton = document.getElementById('invoice-payment-submit');
                const paymentAmountInput = document.getElementById('invoice-payment-amount-input');
                const paymentReceiveInput = document.getElementById('invoice-recieve-amount-input');
                const paymentCurrencySelect = document.getElementById('invoice-payment-currency');
                const paymentCurrencyInput = document.getElementById('invoice-payment-currency-input');
                const paymentRateNote = document.getElementById('invoice-payment-rate-note');
                const baseSubtotal = Number(@json($subtotal));
                const baseDiscountRate = Number(@json($discountRate));

                if (!addButton || !rowsContainer || !productTemplate) {
                    return;
                }

                let rowIndex = 0;
                let latestGrandTotal = Number.isFinite(@json($grandTotal)) ? Number(@json($grandTotal)) : 0;
                let allowConfirmedSubmit = false;

                const updateState = () => {
                    const hasRows = rowsContainer.querySelectorAll('tr').length > 0;
                    const hasInvalidStock = Array.from(rowsContainer.querySelectorAll('tr'))
                        .some((row) => row.dataset.stockInvalid === '1');
                    const hasClient = !isNewInvoice || (clientSelect && clientSelect.value !== '');
                    addButton.disabled = !hasClient;
                    if (saveButton) {
                        saveButton.disabled = !(hasRows && hasClient && !hasInvalidStock);
                    }
                    if (emptyRow) {
                        emptyRow.style.display = hasRows ? 'none' : '';
                    }
                };

                const formatCurrency = (value) => {
                    if (Number.isNaN(value)) {
                        return 'N/A';
                    }
                    return `$${value.toFixed(2)}`;
                };

                const roundMoney = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

                const selectedCurrencyRate = () => {
                    if (!paymentCurrencySelect) {
                        return 1;
                    }
                    const selected = paymentCurrencySelect.options[paymentCurrencySelect.selectedIndex];
                    const rate = Number(selected?.dataset?.rate || 1);
                    if (!Number.isFinite(rate) || rate <= 0) {
                        return 1;
                    }
                    return rate;
                };

                const selectedCurrencyCode = () => {
                    if (!paymentCurrencySelect) {
                        return 'USD';
                    }
                    const selected = paymentCurrencySelect.options[paymentCurrencySelect.selectedIndex];
                    const code = String(selected?.dataset?.code || '').trim();
                    if (code !== '') {
                        return code;
                    }
                    const value = String(paymentCurrencySelect.value || '').trim();
                    if (value !== '') {
                        return value;
                    }
                    return String(selected?.textContent || 'USD').trim();
                };

                const formatSelectedCurrency = (value) => {
                    if (!Number.isFinite(value)) {
                        return 'N/A';
                    }
                    return `${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${selectedCurrencyCode()}`;
                };

                const updateExchangeRateNote = () => {
                    if (!paymentRateNote) {
                        return;
                    }
                    const rate = selectedCurrencyRate();
                    paymentRateNote.textContent = `EXCHANGE_RATE_TO_USD: 1 USD = ${rate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${selectedCurrencyCode()}`;
                };

                const updatePaymentAmountPreview = () => {
                    if (!paymentAmountText) {
                        return;
                    }
                    const rate = selectedCurrencyRate();
                    const amountInSelectedCurrency = roundMoney(latestGrandTotal * rate);
                    paymentAmountText.textContent = formatSelectedCurrency(amountInSelectedCurrency);
                };

                const currentDiscountRate = () => {
                    if (!discountField) {
                        return Number.isFinite(baseDiscountRate) ? Math.max(0, baseDiscountRate) : 0;
                    }
                    const raw = String(discountField.value || '').replace('%', '').trim();
                    const percent = Number(raw);
                    if (!Number.isFinite(percent) || percent <= 0) {
                        return 0;
                    }
                    return percent / 100;
                };

                const updateTotals = () => {
                    const rows = Array.from(rowsContainer.querySelectorAll('tr'));
                    const pendingSubtotal = rows.reduce((sum, row) => {
                        const select = row.querySelector('select');
                        const qtyInput = row.querySelector('input[type="number"]');
                        const productNo = (select?.value || '').trim();
                        if (!productNo) {
                            return sum;
                        }

                        const qty = Math.max(1, Number(qtyInput?.value || 1));
                        const price = Number(row.dataset.price || 0);
                        if (!Number.isFinite(qty) || !Number.isFinite(price) || price < 0) {
                            return sum;
                        }

                        return sum + (qty * price);
                    }, 0);

                    const subtotal = roundMoney((Number.isFinite(baseSubtotal) ? baseSubtotal : 0) + pendingSubtotal);
                    const discountRate = currentDiscountRate();
                    const discountAmount = roundMoney(subtotal * discountRate);
                    const grandTotal = roundMoney(Math.max(0, subtotal - discountAmount));
                    latestGrandTotal = grandTotal;

                    if (subtotalValue) {
                        subtotalValue.textContent = formatCurrency(subtotal);
                    }
                    if (discountValue) {
                        discountValue.textContent = `-${formatCurrency(discountAmount)}`;
                    }
                    if (grandTotalValue) {
                        grandTotalValue.textContent = formatCurrency(grandTotal);
                    }
                };

                const updateDebtPreview = () => {
                    if (!paymentDebtText) {
                        return;
                    }
                    const rate = selectedCurrencyRate();
                    const amountInSelectedCurrency = roundMoney(latestGrandTotal * rate);
                    const receive = Number(receiveAmountInput?.value || 0);
                    const normalizedReceive = Number.isFinite(receive) ? Math.max(0, receive) : 0;
                    const debt = roundMoney(Math.max(0, amountInSelectedCurrency - normalizedReceive));
                    const change = roundMoney(Math.max(0, normalizedReceive - amountInSelectedCurrency));
                    paymentDebtText.textContent = formatSelectedCurrency(debt);
                    if (paymentDebtRow) {
                        paymentDebtRow.style.display = debt > 0 ? '' : 'none';
                    }
                    if (paymentChangeText) {
                        paymentChangeText.textContent = formatSelectedCurrency(change);
                    }
                    if (paymentChangeRow) {
                        paymentChangeRow.style.display = change > 0 ? '' : 'none';
                    }
                };

                const closePaymentModal = () => {
                    if (!paymentModal) {
                        return;
                    }
                    paymentModal.style.display = 'none';
                    paymentModal.setAttribute('aria-hidden', 'true');
                };

                const openPaymentModal = () => {
                    if (!paymentModal || !paymentAmountText || !receiveAmountInput) {
                        return;
                    }
                    if (paymentCurrencySelect && paymentCurrencyInput && paymentCurrencyInput.value) {
                        paymentCurrencySelect.value = paymentCurrencyInput.value;
                    }
                    updateExchangeRateNote();
                    updatePaymentAmountPreview();
                    receiveAmountInput.value = '';
                    updateDebtPreview();
                    paymentModal.style.display = 'flex';
                    paymentModal.setAttribute('aria-hidden', 'false');
                    receiveAmountInput.focus();
                };

                receiveAmountInput?.addEventListener('input', () => {
                    updateDebtPreview();
                });

                paymentCurrencySelect?.addEventListener('change', () => {
                    updateExchangeRateNote();
                    updatePaymentAmountPreview();
                    updateDebtPreview();
                });

                paymentCancelButton?.addEventListener('click', () => {
                    closePaymentModal();
                });

                paymentSubmitButton?.addEventListener('click', () => {
                    if (!activeForm) {
                        return;
                    }
                    const receive = Number(receiveAmountInput?.value || 0);
                    const normalizedReceive = Number.isFinite(receive) ? Math.max(0, receive) : 0;
                    const exchangeRateToUsd = selectedCurrencyRate();
                    const amountInSelectedCurrency = roundMoney(latestGrandTotal * exchangeRateToUsd);
                    if (paymentAmountInput) {
                        paymentAmountInput.value = amountInSelectedCurrency.toFixed(2);
                    }
                    if (paymentReceiveInput) {
                        paymentReceiveInput.value = normalizedReceive.toFixed(2);
                    }
                    if (paymentCurrencyInput && paymentCurrencySelect) {
                        paymentCurrencyInput.value = String(paymentCurrencySelect.value || '').trim();
                    }
                    allowConfirmedSubmit = true;
                    closePaymentModal();
                    if (typeof activeForm.requestSubmit === 'function') {
                        activeForm.requestSubmit(saveButton || undefined);
                    } else {
                        activeForm.submit();
                    }
                });

                paymentModal?.addEventListener('click', (event) => {
                    if (event.target === paymentModal) {
                        closePaymentModal();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && paymentModal?.style.display === 'flex') {
                        closePaymentModal();
                    }
                });

                const setRowStockValidation = (row) => {
                    const select = row.querySelector('select');
                    const qtyInput = row.querySelector('input[type="number"]');
                    const warning = row.querySelector('.js-stock-warning');
                    if (!select || !qtyInput) {
                        return false;
                    }

                    const productNo = (select.value || '').trim();
                    if (productNo === '') {
                        row.dataset.stockInvalid = '0';
                        qtyInput.setCustomValidity('');
                        if (warning) {
                            warning.style.display = 'none';
                            warning.textContent = '';
                        }
                        updateState();
                        return false;
                    }

                    const available = Number(row.dataset.qtyOnHand ?? Number.NaN);
                    const hasLiveStock = Number.isFinite(available);

                    if (!hasLiveStock) {
                        row.dataset.stockInvalid = '1';
                        qtyInput.setCustomValidity('Checking live stock from PRODUCTS.QTY_ON_HAND.');
                        if (warning) {
                            warning.style.display = '';
                            warning.style.color = '#666';
                            warning.textContent = 'Checking live stock...';
                        }
                        updateState();
                        return true;
                    }

                    const qtyValue = Math.max(1, Number(qtyInput.value || 1));
                    const invalid = qtyValue > available;
                    row.dataset.stockInvalid = invalid ? '1' : '0';
                    qtyInput.setCustomValidity(invalid ? `Only ${available} unit(s) are available.` : '');

                    if (warning) {
                        if (invalid) {
                            warning.textContent = `Only ${available} available.`;
                            warning.style.color = '#a32222';
                            warning.style.display = '';
                        } else {
                            warning.textContent = '';
                            warning.style.display = 'none';
                        }
                    }

                    updateState();
                    return invalid;
                };

                const updateRowTotals = (row, price) => {
                    const qtyInput = row.querySelector('input[type="number"]');
                    const priceCell = row.querySelector('.js-price');
                    const amountCell = row.querySelector('.js-amount');
                    const qty = qtyInput ? Math.max(1, Number(qtyInput.value || 1)) : 1;
                    if (priceCell) {
                        priceCell.textContent = formatCurrency(price);
                    }
                    if (amountCell) {
                        amountCell.textContent = formatCurrency(price * qty);
                    }
                };

                const resetRowTotals = (row) => {
                    row.dataset.price = '';
                    row.dataset.qtyOnHand = '';
                    const priceCell = row.querySelector('.js-price');
                    const amountCell = row.querySelector('.js-amount');
                    if (priceCell) {
                        priceCell.textContent = 'Auto';
                    }
                    if (amountCell) {
                        amountCell.textContent = 'Auto';
                    }
                    setRowStockValidation(row);
                    updateTotals();
                };

                const fetchPrice = async (row) => {
                    const select = row.querySelector('select');
                    const qtyInput = row.querySelector('input[type="number"]');
                    if (!select || !qtyInput) {
                        return;
                    }
                    const productNo = select.value;
                    if (!productNo) {
                        resetRowTotals(row);
                        return;
                    }

                    const requestSeq = Number(row.dataset.requestSeq || '0') + 1;
                    row.dataset.requestSeq = String(requestSeq);
                    row.dataset.qtyOnHand = '';
                    setRowStockValidation(row);

                    const url = new URL(priceEndpoint, window.location.origin);
                    url.searchParams.set('product_no', productNo);
                    url.searchParams.set('qty', qtyInput.value || '1');

                    try {
                        const response = await fetch(url.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!response.ok) {
                            if (row.dataset.requestSeq !== String(requestSeq)) {
                                return;
                            }
                            resetRowTotals(row);
                            return;
                        }
                        const data = await response.json();
                        if (row.dataset.requestSeq !== String(requestSeq)) {
                            return;
                        }
                        const price = Number(data.price || 0);
                        const qtyOnHand = Number(data.qty_on_hand ?? data.available_stock ?? Number.NaN);
                        row.dataset.price = String(price);
                        row.dataset.qtyOnHand = Number.isFinite(qtyOnHand) ? String(qtyOnHand) : '';
                        updateRowTotals(row, price);
                        setRowStockValidation(row);
                        updateTotals();
                    } catch (error) {
                        if (row.dataset.requestSeq !== String(requestSeq)) {
                            return;
                        }
                        resetRowTotals(row);
                    }
                };

                const buildRow = () => {
                    const row = document.createElement('tr');

                    const photoCell = document.createElement('td');
                    photoCell.className = 'subtle';
                    photoCell.style.textAlign = 'center';
                    photoCell.textContent = 'Auto';

                    const productCell = document.createElement('td');
                    const productSelect = productTemplate.cloneNode(true);
                    productSelect.id = '';
                    productSelect.style.display = '';
                    productSelect.name = `items[${rowIndex}][product_no]`;
                    if (activeFormId) {
                        productSelect.setAttribute('form', activeFormId);
                    }
                    productSelect.required = true;
                    productCell.appendChild(productSelect);

                    const qtyCell = document.createElement('td');
                    qtyCell.style.textAlign = 'right';
                    const qtyInput = document.createElement('input');
                    qtyInput.type = 'number';
                    qtyInput.name = `items[${rowIndex}][qty]`;
                    qtyInput.min = '1';
                    qtyInput.max = '9999';
                    qtyInput.value = '1';
                    if (activeFormId) {
                        qtyInput.setAttribute('form', activeFormId);
                    }
                    qtyInput.required = true;
                    qtyInput.style.textAlign = 'right';
                    qtyCell.appendChild(qtyInput);

                    const stockWarning = document.createElement('div');
                    stockWarning.className = 'js-stock-warning';
                    stockWarning.style.marginTop = '6px';
                    stockWarning.style.fontSize = '.8rem';
                    stockWarning.style.color = '#a32222';
                    stockWarning.style.display = 'none';
                    qtyCell.appendChild(stockWarning);

                    const priceCell = document.createElement('td');
                    priceCell.style.textAlign = 'right';
                    priceCell.className = 'subtle js-price';
                    priceCell.textContent = 'Auto';

                    const amountCell = document.createElement('td');
                    amountCell.style.textAlign = 'right';
                    amountCell.className = 'subtle js-amount';
                    amountCell.textContent = 'Auto';

                    const actionCell = document.createElement('td');
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'btn btn-danger js-remove-row';
                    removeButton.textContent = 'Remove';
                    actionCell.appendChild(removeButton);

                    row.append(photoCell, productCell, qtyCell, priceCell, amountCell, actionCell);
                    row.dataset.stockInvalid = '0';
                    row.dataset.qtyOnHand = '';
                    row.dataset.requestSeq = '0';
                    rowIndex += 1;
                    return row;
                };

                addButton.addEventListener('click', () => {
                    rowsContainer.appendChild(buildRow());
                    updateState();
                    updateTotals();
                });

                if (clientSelect && isNewInvoice) {
                    clientSelect.addEventListener('change', () => {
                        updateState();
                        updateTotals();
                    });
                }

                rowsContainer.addEventListener('change', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }
                    if (target.tagName === 'SELECT') {
                        const row = target.closest('tr');
                        if (row) {
                            row.dataset.qtyOnHand = '';
                            setRowStockValidation(row);
                            fetchPrice(row);
                            updateTotals();
                        }
                        return;
                    }
                    if (target.tagName === 'INPUT' && target.getAttribute('type') === 'number') {
                        const row = target.closest('tr');
                        if (row) {
                            fetchPrice(row);
                            updateTotals();
                        }
                    }
                });

                rowsContainer.addEventListener('input', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }
                    if (target.tagName === 'INPUT' && target.getAttribute('type') === 'number') {
                        const row = target.closest('tr');
                        if (!row) {
                            return;
                        }
                        const price = Number(row.dataset.price || 0);
                        if (!Number.isNaN(price) && price > 0) {
                            updateRowTotals(row, price);
                        }
                        setRowStockValidation(row);
                        updateTotals();
                    }
                });

                rowsContainer.addEventListener('click', (event) => {
                    const target = event.target;
                    if (target instanceof HTMLElement && target.classList.contains('js-remove-row')) {
                        const row = target.closest('tr');
                        if (row) {
                            row.remove();
                            updateState();
                            updateTotals();
                        }
                    }
                });

                activeForm?.addEventListener('submit', (event) => {
                    const firstInvalid = Array.from(rowsContainer.querySelectorAll('tr'))
                        .find((row) => setRowStockValidation(row));
                    if (!firstInvalid) {
                        if (allowConfirmedSubmit) {
                            allowConfirmedSubmit = false;
                            return;
                        }
                        event.preventDefault();
                        openPaymentModal();
                        return;
                    }
                    event.preventDefault();
                    const qtyInput = firstInvalid.querySelector('input[type="number"]');
                    if (qtyInput instanceof HTMLElement) {
                        qtyInput.focus();
                    }
                });

                updateState();
                updateTotals();
            })();
        </script>
    @endif

    @if(session('bakong_qr'))
        <div
            id="bakong-qr-modal"
            style="position: fixed; inset: 0; background: rgba(10, 14, 20, 0.55); display: flex; align-items: center; justify-content: center; z-index: 1100; padding: 16px;"
        >
            <div class="card" role="dialog" aria-modal="true" style="width: min(420px, 100%); margin: 0; text-align: center;">
                <div style="display: flex; align-items: center; gap: 10px; justify-content: center; margin-bottom: 12px;">
                    <img src="https://bakong.nbc.gov.kh/images/logo.svg" alt="Bakong" style="height: 36px;" onerror="this.style.display='none'">
                    <h3 style="margin: 0;">Bakong KHQR Payment</h3>
                </div>
                <p style="margin: 0 0 8px; font-size: .9rem; color: #666;">Scan this QR code with any Bakong-supported app to pay</p>
                <div style="display: flex; align-items: baseline; justify-content: center; gap: 6px; margin-bottom: 16px;">
                    <strong style="font-size: 1.6rem; color: #1a6b3c;">${{ number_format((float) session('bakong_qr_amount', 0), 2) }}</strong>
                    <span style="font-size: .95rem; color: #888;">USD</span>
                </div>
                <div id="bakong-qr-container" style="display: inline-block; padding: 12px; background: #fff; border-radius: 12px; border: 2px solid #e5e7eb;"></div>
                <div style="margin-top: 16px;">
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('bakong-qr-modal').style.display='none';" style="min-width: 120px;">Close</button>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
        <script>
            (() => {
                const qrString = @json(session('bakong_qr'));
                if (!qrString) return;
                const qr = qrcode(0, 'M');
                qr.addData(qrString);
                qr.make();
                const container = document.getElementById('bakong-qr-container');
                if (container) {
                    container.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
                    const svg = container.querySelector('svg');
                    if (svg) {
                        svg.style.width = '260px';
                        svg.style.height = '260px';
                    }
                }
            })();
        </script>
    @endif
@endsection
