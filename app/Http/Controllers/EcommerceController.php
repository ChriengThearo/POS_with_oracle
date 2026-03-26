<?php

namespace App\Http\Controllers;

use App\Support\BakongQR;
use App\Support\SessionCart;
use App\Support\StaffAuth;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class EcommerceController extends Controller
{
    private const ORACLE_CONNECTION = 'oracle';

    private const INVOICE_STATUSES = ['UNPAID', 'PAID', 'PARTIAL', 'In Process'];

    /**
     * Cache for table/column identity sequence names.
     *
     * @var array<string, string>
     */
    private array $identitySequences = [];

    public function __construct(private readonly SessionCart $cart) {}

    public function home(): View
    {
        $conn = $this->db();

        $metrics = [
            'products' => (int) $conn->table('PRODUCTS')->count(),
            'clients' => (int) $conn->table('CLIENTS')->count(),
            'invoices' => (int) $conn->table('INVOICES')->count(),
            'revenue' => (float) ($conn->table('INVOICE_DETAILS')
                ->selectRaw('NVL(SUM(QTY * PRICE), 0) AS total_revenue')
                ->value('total_revenue') ?? 0),
        ];

        $inProcessCount = (int) $conn->table('INVOICES')
            ->where('INVOICE_STATUS', '=', 'In Process')
            ->count();

        $recentInvoices = $conn->table('INVOICES as i')
            ->join('CLIENTS as c', 'c.CLIENT_NO', '=', 'i.CLIENT_NO')
            ->join('EMPLOYEES as e', 'e.EMPLOYEE_ID', '=', 'i.EMPLOYEE_ID')
            ->leftJoin('INVOICE_DETAILS as d', 'd.INVOICE_NO', '=', 'i.INVOICE_NO')
            ->selectRaw('
                i.INVOICE_NO as invoice_no,
                i.INVOICE_DATE as invoice_date,
                c.CLIENT_NAME as client_name,
                e.EMPLOYEE_NAME as seller,
                i.INVOICE_STATUS as invoice_status,
                NVL(SUM(d.QTY * d.PRICE), 0) as subtotal
            ')
            ->groupBy('i.INVOICE_NO', 'i.INVOICE_DATE', 'c.CLIENT_NAME', 'e.EMPLOYEE_NAME', 'i.INVOICE_STATUS')
            ->orderByDesc('i.INVOICE_NO')
            ->limit(8)
            ->get();

        $lowStocks = $conn->table('ALERT_STOCKS as a')
            ->join('PRODUCTS as p', 'p.PRODUCT_NO', '=', 'a.PRODUCT_NO')
            ->selectRaw('
                a.ALERT_STOCK_NO as alert_stock_no,
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                p.QTY_ON_HAND as qty_on_hand,
                a.LOWER_QTY as lower_qty,
                a.HIGHER_QTY as higher_qty,
                p.STATUS as stock_status
            ')
            ->orderBy('p.QTY_ON_HAND')
            ->limit(8)
            ->get();

        $topProducts = $conn->table('MONTHLY_SALES')
            ->selectRaw('
                PRODUCT_NO as product_no,
                PRODUCT_NAME as product_name,
                NVL(SUM(SALES), 0) as sales,
                NVL(SUM(UNITS), 0) as units
            ')
            ->groupBy('PRODUCT_NO', 'PRODUCT_NAME')
            ->orderByDesc('sales')
            ->limit(6)
            ->get();

        return view('ecommerce.home', [
            'metrics' => $metrics,
            'recentInvoices' => $recentInvoices,
            'lowStocks' => $lowStocks,
            'topProducts' => $topProducts,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $inProcessCount,
        ]);
    }

    public function catalog(Request $request): View
    {
        $conn = $this->db();
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $stock = trim((string) $request->query('stock', ''));

        $query = $conn->table('PRODUCTS as p')
            ->leftJoin('PRODUCT_TYPE as t', 't.PRODUCTTYPE_ID', '=', 'p.PRODUCT_TYPE')
            ->selectRaw('
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                p.PRODUCT_TYPE as product_type_id,
                t.PRODUCTYPE_NAME as product_type_name,
                p.SELL_PRICE as sell_price,
                p.COST_PRICE as cost_price,
                p.PROFIT_PERCENT as profit_percent,
                p.UNIT_MEASURE as unit_measure,
                p.QTY_ON_HAND as qty_on_hand,
                p.STATUS as stock_status
            ');

        if ($q !== '') {
            $keyword = '%'.mb_strtoupper($q).'%';
            $query->where(function ($sub) use ($keyword): void {
                $sub->whereRaw('UPPER(p.PRODUCT_NAME) LIKE ?', [$keyword])
                    ->orWhereRaw('UPPER(p.PRODUCT_NO) LIKE ?', [$keyword]);
            });
        }

        if ($type !== '' && ctype_digit($type)) {
            $query->where('p.PRODUCT_TYPE', '=', (int) $type);
        }

        if ($stock !== '') {
            if ($stock === 'in_stock') {
                $query->where('p.QTY_ON_HAND', '>', 0);
            }
            if ($stock === 'understock') {
                $query->whereRaw('UPPER(NVL(p.STATUS, \'UNKNOWN\')) = ?', ['UNDERSTOCK']);
            }
            if ($stock === 'overstock') {
                $query->whereRaw('UPPER(NVL(p.STATUS, \'UNKNOWN\')) = ?', ['OVERSTOCK']);
            }
        }

        $products = $query
            ->orderBy('p.PRODUCT_NAME')
            ->paginate(12)
            ->appends($request->query());

        $types = $conn->table('PRODUCT_TYPE')
            ->selectRaw('PRODUCTTYPE_ID as id, PRODUCTYPE_NAME as name')
            ->orderBy('PRODUCTYPE_NAME')
            ->get();

        return view('ecommerce.catalog', [
            'products' => $products,
            'types' => $types,
            'q' => $q,
            'type' => $type,
            'stock' => $stock,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function cart(): View
    {
        $items = $this->cartItemsWithLiveData();

        return view('ecommerce.cart', [
            'items' => $items,
            'totals' => $this->calculateTotals($items),
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_no' => ['required', 'string', 'max:20'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $productNo = trim((string) $validated['product_no']);
        $qty = (int) ($validated['qty'] ?? 1);
        $product = $this->fetchProduct($productNo);
        if (! $product) {
            return back()->with('error', 'Product was not found.');
        }

        $existingQty = (int) (($this->cart->get($productNo)['qty'] ?? 0));
        $available = (int) $product->qty_on_hand;
        if (($existingQty + $qty) > $available) {
            return back()->with('error', "Only {$available} unit(s) are available for this product.");
        }

        $this->cart->add((array) $product, $qty);

        return back()->with('success', 'Product added to cart.');
    }

    public function updateCart(Request $request, string $productNo): RedirectResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        if (! $this->cart->get($productNo)) {
            return back()->with('error', 'Cart item no longer exists.');
        }

        $qty = (int) $validated['qty'];
        if ($qty === 0) {
            $this->cart->remove($productNo);

            return back()->with('success', 'Item removed from cart.');
        }

        $product = $this->fetchProduct($productNo);
        if (! $product) {
            $this->cart->remove($productNo);

            return back()->with('error', 'Product no longer exists in catalog.');
        }

        $available = (int) $product->qty_on_hand;
        if ($qty > $available) {
            return back()->with('error', "Only {$available} unit(s) are available for this product.");
        }

        $this->cart->setQuantity($productNo, $qty);

        return back()->with('success', 'Cart updated.');
    }

    public function removeFromCart(string $productNo): RedirectResponse
    {
        $this->cart->remove($productNo);

        return back()->with('success', 'Item removed from cart.');
    }

    public function clearCart(): RedirectResponse
    {
        $this->cart->clear();

        return back()->with('success', 'Cart cleared.');
    }

    public function checkout(): RedirectResponse|View
    {
        $items = $this->cartItemsWithLiveData();
        if ($items->isEmpty()) {
            return redirect()->route('store.catalog')->with('error', 'Your cart is empty.');
        }

        $hasUnavailable = $items->contains(fn (array $item): bool => ! $item['exists'] || $item['qty'] > $item['available_stock']);
        if ($hasUnavailable) {
            return redirect()->route('store.cart')->with('error', 'Please review cart quantities before checking out.');
        }

        $conn = $this->db();
        $clientTypes = $conn->table('CLIENT_TYPE')
            ->selectRaw('CLIENTTYPE_ID as clienttype_id, TYPE_NAME as type_name, DISCOUNT_RATE as discount_rate')
            ->orderBy('CLIENTTYPE_ID')
            ->get();

        $defaultClientType = $clientTypes->firstWhere('type_name', 'Normal') ?? $clientTypes->first();
        $staff = StaffAuth::user();

        return view('ecommerce.checkout', [
            'items' => $items,
            'totals' => $this->calculateTotals($items),
            'clientTypes' => $clientTypes,
            'defaultClientType' => $defaultClientType?->clienttype_id,
            'staff' => $staff,
            'invoiceStatuses' => self::INVOICE_STATUSES,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:50'],
            'client_type' => ['nullable', 'integer'],
            'invoice_status' => ['required', Rule::in(self::INVOICE_STATUSES)],
            'invoice_memo' => ['nullable', 'string', 'max:100'],
        ]);

        $items = $this->cartItemsWithLiveData();
        if ($items->isEmpty()) {
            return redirect()->route('store.catalog')->with('error', 'Your cart is empty.');
        }

        $insufficient = $items->first(fn (array $item): bool => ! $item['exists'] || $item['qty'] > $item['available_stock']);
        if ($insufficient) {
            return redirect()->route('store.cart')
                ->with('error', "Stock changed for {$insufficient['product_name']}. Please review your cart.");
        }

        $staff = StaffAuth::user();
        $employeeId = (int) ($staff['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return redirect()->route('staff.login')->with('error', 'Staff session expired. Please log in again.');
        }

        $conn = $this->db();
        $clientType = $this->resolveClientType($validated['client_type'] ?? null);

        try {
            $invoiceNo = $conn->transaction(function () use ($validated, $items, $clientType, $employeeId): int {
                $clientNo = $this->upsertClient($validated, $clientType);
                $invoiceNo = $this->createInvoice(
                    $clientNo,
                    $employeeId,
                    (string) $validated['invoice_status'],
                    (string) ($validated['invoice_memo'] ?? '')
                );

                foreach ($items as $item) {
                    $this->db()->insert(
                        'INSERT INTO INVOICE_DETAILS (INVOICE_NO, PRODUCT_NO, QTY) VALUES (:invoice_no, :product_no, :qty)',
                        [
                            'invoice_no' => $invoiceNo,
                            'product_no' => (string) $item['product_no'],
                            'qty' => (int) $item['qty'],
                        ]
                    );
                }

                return $invoiceNo;
            }, 3);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'Order failed due to a database rule: '.$e->getMessage());
        }

        $this->cart->clear();

        return redirect()
            ->route('store.orders.show', ['invoiceNo' => $invoiceNo])
            ->with('success', "Order #{$invoiceNo} was created successfully.");
    }

    public function orders(Request $request): View
    {
        $conn = $this->db();
        $q = trim((string) $request->query('q', ''));
        $status = 'In Process';

        $orders = $this->buildOrderQuery($q, $status)
            ->orderByDesc('i.INVOICE_NO')
            ->paginate(15)
            ->appends($request->query());

        return view('ecommerce.orders', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'statuses' => collect([$status]),
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function totalSales(Request $request): View
    {
        $conn = $this->db();
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $clientNo = trim((string) $request->query('client_no', ''));
        $q = trim((string) $request->query('q', ''));

        $query = $conn->table('INVOICES as i')
            ->join('CLIENTS as c', 'c.CLIENT_NO', '=', 'i.CLIENT_NO')
            ->join('EMPLOYEES as e', 'e.EMPLOYEE_ID', '=', 'i.EMPLOYEE_ID')
            ->leftJoin('INVOICE_DETAILS as d', 'd.INVOICE_NO', '=', 'i.INVOICE_NO')
            ->selectRaw('
                i.INVOICE_NO as invoice_no,
                i.INVOICE_DATE as invoice_date,
                e.EMPLOYEE_NAME as seller,
                c.CLIENT_NO as client_no,
                c.CLIENT_NAME as client_name,
                c.DISCOUNT as client_discount,
                i.INVOICE_STATUS as invoice_status,
                NVL(SUM(d.QTY), 0) as item_qty,
                NVL(SUM(d.QTY * d.PRICE), 0) as subtotal
            ')
            ->groupBy(
                'i.INVOICE_NO',
                'i.INVOICE_DATE',
                'e.EMPLOYEE_NAME',
                'c.CLIENT_NO',
                'c.CLIENT_NAME',
                'c.DISCOUNT',
                'i.INVOICE_STATUS'
            );

        if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $query->whereRaw("TRUNC(i.INVOICE_DATE) >= TO_DATE(?, 'YYYY-MM-DD')", [$fromDate]);
        }

        if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $query->whereRaw("TRUNC(i.INVOICE_DATE) <= TO_DATE(?, 'YYYY-MM-DD')", [$toDate]);
        }

        if ($clientNo !== '') {
            if (ctype_digit($clientNo)) {
                $query->where('c.CLIENT_NO', '=', (int) $clientNo);
            } else {
                $query->where('c.CLIENT_NO', '=', $clientNo);
            }
        }

        $decorateRow = function (object $row): object {
            $subtotal = (float) ($row->subtotal ?? 0);
            $discountRate = $this->normalizedDiscountRate((float) ($row->client_discount ?? 0));
            $discountAmount = round($subtotal * $discountRate, 2);
            $balance = round(max(0, $subtotal - $discountAmount), 2);

            $row->discount_rate = $discountRate;
            $row->discount_amount = $discountAmount;
            $row->balance = $balance;

            return $row;
        };

        if ($q !== '') {
            $allRows = $query
                ->orderByDesc('i.INVOICE_NO')
                ->get()
                ->map($decorateRow)
                ->filter(function (object $row) use ($q): bool {
                    $keyword = mb_strtoupper($q);
                    $invoiceDate = $row->invoice_date
                        ? \Illuminate\Support\Carbon::parse($row->invoice_date)->format('Y-m-d')
                        : '';

                    $searchValues = [
                        (string) ($row->invoice_no ?? ''),
                        (string) $invoiceDate,
                        (string) ($row->seller ?? ''),
                        (string) ($row->client_no ?? ''),
                        (string) ($row->client_name ?? ''),
                        (string) ($row->item_qty ?? ''),
                        number_format((float) ($row->subtotal ?? 0), 2, '.', ''),
                        number_format((float) (($row->discount_rate ?? 0) * 100), 2, '.', ''),
                        number_format((float) ($row->balance ?? 0), 2, '.', ''),
                        (string) ($row->invoice_status ?? ''),
                    ];

                    $haystack = mb_strtoupper(implode(' ', $searchValues));

                    return str_contains($haystack, $keyword);
                })
                ->values();

            $perPage = 15;
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $pageItems = $allRows->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $sales = new LengthAwarePaginator(
                $pageItems,
                $allRows->count(),
                $perPage,
                $currentPage,
                [
                    'path' => route('total-sales.index'),
                    'query' => $request->query(),
                ]
            );
        } else {
            $sales = $query
                ->orderByDesc('i.INVOICE_NO')
                ->paginate(15)
                ->appends($request->query());

            $sales->getCollection()->transform($decorateRow);
        }

        $clients = $conn->table('CLIENTS')
            ->selectRaw('CLIENT_NO as client_no, CLIENT_NAME as client_name')
            ->orderBy('CLIENT_NAME')
            ->get();

        return view('ecommerce.total-sales', [
            'sales' => $sales,
            'clients' => $clients,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'clientNo' => $clientNo,
            'q' => $q,
            'cartCount' => $this->cart->totalQuantity(),
        ]);
    }

    public function clientDepts(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $query = $this->db()->table('INVOICES as i')
            ->join('CLIENTS as c', 'c.CLIENT_NO', '=', 'i.CLIENT_NO')
            ->selectRaw('
                i.INVOICE_NO as invoice_no,
                i.INVOICE_DATE as invoice_date,
                i.INVOICE_STATUS as invoice_status,
                c.CLIENT_NO as client_no,
                c.CLIENT_NAME as client_name,
                c.PHONE as phone
            ')
            ->where('i.INVOICE_STATUS', '=', 'In Debt');

        if ($q !== '') {
            $upper = '%'.mb_strtoupper($q).'%';
            $query->where(function ($sub) use ($q, $upper): void {
                if (ctype_digit($q)) {
                    $sub->where('i.INVOICE_NO', '=', (int) $q)
                        ->orWhere('c.CLIENT_NO', '=', (int) $q)
                        ->orWhereRaw('UPPER(c.CLIENT_NAME) LIKE ?', [$upper])
                        ->orWhereRaw('c.PHONE LIKE ?', ['%'.$q.'%']);
                } else {
                    $sub->whereRaw('UPPER(c.CLIENT_NAME) LIKE ?', [$upper])
                        ->orWhereRaw('c.PHONE LIKE ?', ['%'.$q.'%']);
                }
            });
        }

        $rows = $query
            ->orderByDesc('i.INVOICE_NO')
            ->paginate(15)
            ->appends($request->query());

        $rows->getCollection()->transform(function (object $row): object {
            $invoiceNo = (int) ($row->invoice_no ?? 0);
            if ($invoiceNo <= 0) {
                $row->amount = 0.0;
                $row->recieve_amount = 0.0;
                $row->debt_amount = 0.0;

                return $row;
            }

            try {
                $amount = $this->invoiceGrandTotal($invoiceNo);
            } catch (\Throwable) {
                $amount = 0.0;
            }

            $recieved = $this->resolveInvoiceReceivedAmount($invoiceNo);
            $debt = round(max(0, $amount - $recieved), 2);

            $row->amount = $amount;
            $row->recieve_amount = $recieved;
            $row->debt_amount = $debt;

            return $row;
        });

        return view('ecommerce.client-depts', [
            'rows' => $rows,
            'q' => $q,
            'errorMessage' => null,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function deptHistory(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $columns = $this->tableColumns('PAYMENTS');
        $invoiceCol = $this->pickColumn($columns, ['INVOICE_NO']);
        $paymentIdCol = $this->pickColumn($columns, ['PAYMENT_ID', 'PAYMENT_NO']);
        $recieveCol = $this->pickColumn($columns, ['RECIEVE_AMOUNT', 'RECEIVE_AMOUNT', 'USD']);
        $createAtCol = $this->pickColumn($columns, ['CREATE_AT', 'CREATED_AT']);

        if (! $invoiceCol || ! $paymentIdCol || ! $recieveCol) {
            return view('ecommerce.dept-history', [
                'rows' => collect(),
                'q' => $q,
                'errorMessage' => 'Dept history is unavailable because PAYMENTS schema is missing required columns.',
                'cartCount' => $this->cart->totalQuantity(),
                'inProcessCount' => $this->countInProcessInvoices(),
            ]);
        }

        $paymentDateSelect = $createAtCol
            ? 'p.'.$createAtCol.' as payment_date'
            : 'NULL as payment_date';

        $query = $this->db()->table('PAYMENTS as p')
            ->join('INVOICES as i', static function ($join) use ($invoiceCol): void {
                $join->whereRaw('TO_CHAR(i.INVOICE_NO) = p.'.$invoiceCol);
            })
            ->join('CLIENTS as c', 'c.CLIENT_NO', '=', 'i.CLIENT_NO')
            ->selectRaw('
                p.'.$paymentIdCol.' as payment_id,
                i.INVOICE_NO as invoice_no,
                i.INVOICE_DATE as invoice_date,
                i.INVOICE_STATUS as invoice_status,
                c.CLIENT_NO as client_no,
                c.CLIENT_NAME as client_name,
                c.PHONE as phone,
                NVL(p.'.$recieveCol.', 0) as recieve_amount,
                NVL(SUM(NVL(p.'.$recieveCol.', 0)) OVER (
                    PARTITION BY p.'.$invoiceCol.'
                    ORDER BY p.'.$paymentIdCol.'
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ), 0) as recieved_to_date,
                '.$paymentDateSelect.'
            ');

        if ($q !== '') {
            $upper = '%'.mb_strtoupper($q).'%';
            $query->where(function ($sub) use ($q, $upper, $paymentIdCol): void {
                if (ctype_digit($q)) {
                    $sub->where('i.INVOICE_NO', '=', (int) $q)
                        ->orWhere('c.CLIENT_NO', '=', (int) $q)
                        ->orWhere('p.'.$paymentIdCol, '=', (int) $q)
                        ->orWhereRaw('UPPER(c.CLIENT_NAME) LIKE ?', [$upper])
                        ->orWhereRaw('c.PHONE LIKE ?', ['%'.$q.'%']);
                } else {
                    $sub->whereRaw('UPPER(c.CLIENT_NAME) LIKE ?', [$upper])
                        ->orWhereRaw('c.PHONE LIKE ?', ['%'.$q.'%']);
                }
            });
        }

        $rows = $query
            ->orderByDesc('p.'.$paymentIdCol)
            ->paginate(20)
            ->appends($request->query());

        $invoiceTotals = [];
        $rows->getCollection()->transform(function (object $row) use (&$invoiceTotals): object {
            $invoiceNo = (int) ($row->invoice_no ?? 0);
            if (! array_key_exists($invoiceNo, $invoiceTotals)) {
                try {
                    $invoiceTotals[$invoiceNo] = $this->invoiceGrandTotal($invoiceNo);
                } catch (\Throwable) {
                    $invoiceTotals[$invoiceNo] = 0.0;
                }
            }

            $amount = round(max(0, (float) ($invoiceTotals[$invoiceNo] ?? 0)), 2);
            $recievedToDate = round(max(0, (float) ($row->recieved_to_date ?? 0)), 2);

            $row->amount = $amount;
            $row->debt_amount = round(max(0, $amount - $recievedToDate), 2);
            $row->recieve_amount = round(max(0, (float) ($row->recieve_amount ?? 0)), 2);
            $row->recieved_to_date = $recievedToDate;

            return $row;
        });

        return view('ecommerce.dept-history', [
            'rows' => $rows,
            'q' => $q,
            'errorMessage' => null,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function purchaseHistory(Request $request): View
    {
        $conn = $this->db();
        $purchaseColumns = $this->tableColumns('PURCHASES');
        $detailColumns = $this->tableColumns('PURCHASE_DETAILS');
        $supplierColumns = $this->tableColumns('SUPPLIERS');

        $purchaseNoCol = $this->pickColumn($purchaseColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $purchaseDateCol = $this->pickColumn($purchaseColumns, ['PURCHASE_DATE', 'PUR_DATE', 'DATE', 'CREATED_AT']);
        $statusCol = $this->pickColumn($purchaseColumns, ['PURCHASE_STATUS', 'STATUS']);
        $supplierIdCol = $this->pickColumn($purchaseColumns, ['SUPPLIER_ID', 'SUPLIER_ID', 'SUPPLIER_NO', 'SUP_ID']);
        $employeeIdCol = $this->pickColumn($purchaseColumns, ['EMPLOYEE_ID', 'STAFF_ID']);

        $detailPurchaseCol = $this->pickColumn($detailColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $detailQtyCol = $this->pickColumn($detailColumns, ['QTY', 'QUANTITY']);
        $detailCostCol = $this->pickColumn($detailColumns, ['UNIT_COST', 'UNITCOST', 'UNIT_PRICE', 'PRICE', 'COST_PRICE', 'COST']);

        $supplierKeyCol = $this->pickColumn($supplierColumns, ['SUPPLIER_ID', 'SUPPLIER_NO', 'SUP_ID']);
        $supplierNameCol = $this->pickColumn($supplierColumns, ['SUPPLIER_NAME', 'NAME', 'SUP_NAME']);

        if (! $purchaseNoCol || ! $purchaseDateCol || ! $detailPurchaseCol || ! $detailQtyCol || ! $detailCostCol || ! $supplierIdCol || ! $supplierKeyCol || ! $supplierNameCol) {
            return view('ecommerce.purchase-history', [
                'purchases' => collect(),
                'suppliers' => collect(),
                'fromDate' => '',
                'toDate' => '',
                'supplierId' => '',
                'q' => '',
                'sort' => 'latest',
                'sortOptions' => $this->purchaseHistorySortOptions(),
                'errorMessage' => 'Purchase history is not available because required columns are missing.',
                'cartCount' => $this->cart->totalQuantity(),
                'inProcessCount' => $this->countInProcessInvoices(),
            ]);
        }

        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $supplierId = trim((string) $request->query('supplier_id', ''));
        $q = trim((string) $request->query('q', ''));
        $sort = trim((string) $request->query('sort', 'latest'));
        $sortOptions = $this->purchaseHistorySortOptions();
        if (! array_key_exists($sort, $sortOptions)) {
            $sort = 'latest';
        }

        $query = $conn->table('PURCHASES as p')
            ->leftJoin('SUPPLIERS as s', 's.'.$supplierKeyCol, '=', 'p.'.$supplierIdCol)
            ->leftJoin('PURCHASE_DETAILS as d', 'd.'.$detailPurchaseCol, '=', 'p.'.$purchaseNoCol)
            ->selectRaw('
                p.'.$purchaseNoCol.' as purchase_no,
                p.'.$purchaseDateCol.' as purchase_date,
                s.'.$supplierKeyCol.' as supplier_id,
                s.'.$supplierNameCol.' as supplier_name,
                NVL(SUM(d.'.$detailQtyCol.'), 0) as item_qty,
                NVL(SUM(d.'.$detailQtyCol.' * d.'.$detailCostCol.'), 0) as subtotal,
                p.'.($statusCol ?: $purchaseNoCol).' as purchase_status
            ')
            ->groupBy(
                'p.'.$purchaseNoCol,
                'p.'.$purchaseDateCol,
                's.'.$supplierKeyCol,
                's.'.$supplierNameCol,
                'p.'.($statusCol ?: $purchaseNoCol)
            );

        if ($employeeIdCol) {
            $query->leftJoin('EMPLOYEES as e', 'e.EMPLOYEE_ID', '=', 'p.'.$employeeIdCol);
            $query->addSelect(DB::raw('e.EMPLOYEE_NAME as buyer'));
            $query->groupBy('e.EMPLOYEE_NAME');
        } else {
            $query->addSelect(DB::raw('NULL as buyer'));
        }

        if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && $purchaseDateCol) {
            $query->whereRaw('TRUNC(p.'.$purchaseDateCol.") >= TO_DATE(?, 'YYYY-MM-DD')", [$fromDate]);
        }

        if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) && $purchaseDateCol) {
            $query->whereRaw('TRUNC(p.'.$purchaseDateCol.") <= TO_DATE(?, 'YYYY-MM-DD')", [$toDate]);
        }

        if ($supplierId !== '' && is_numeric($supplierId)) {
            $query->where('p.'.$supplierIdCol, '=', (int) $supplierId);
        }

        if ($q !== '') {
            $upper = '%'.mb_strtoupper($q).'%';
            $query->where(function ($sub) use ($q, $upper, $purchaseNoCol, $supplierNameCol): void {
                if (ctype_digit($q)) {
                    $sub->where('p.'.$purchaseNoCol, '=', (int) $q)
                        ->orWhereRaw('UPPER(s.'.$supplierNameCol.') LIKE ?', [$upper]);
                } else {
                    $sub->whereRaw('UPPER(s.'.$supplierNameCol.') LIKE ?', [$upper]);
                }
            });
        }

        $this->applyPurchaseHistorySort($query, $sort, $purchaseNoCol, $purchaseDateCol);

        $purchases = $query
            ->paginate(15)
            ->appends($request->query());

        $suppliers = $conn->table('SUPPLIERS as s')
            ->selectRaw('s.'.$supplierKeyCol.' as supplier_id, s.'.$supplierNameCol.' as supplier_name')
            ->orderBy('s.'.$supplierNameCol)
            ->get();

        return view('ecommerce.purchase-history', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'supplierId' => $supplierId,
            'q' => $q,
            'sort' => $sort,
            'sortOptions' => $sortOptions,
            'errorMessage' => null,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $conn = $this->db();
        $invoiceNos = $conn->table('INVOICES')
            ->selectRaw('INVOICE_NO as invoice_no')
            ->orderByDesc('INVOICE_NO')
            ->limit(200)
            ->get();

        $products = $conn->table('PRODUCTS')
            ->selectRaw('PRODUCT_NO as product_no, PRODUCT_NAME as product_name, QTY_ON_HAND as qty_on_hand')
            ->orderBy('PRODUCT_NAME')
            ->get();

        $clients = $conn->table('CLIENTS')
            ->selectRaw('CLIENT_NO as client_no, CLIENT_NAME as client_name, DISCOUNT as discount, ADDRESS as address, CITY as city, PHONE as phone')
            ->orderBy('CLIENT_NAME')
            ->get();

        $currencyOptions = $this->paymentCurrencyOptions();
        $paymentCurrencies = $currencyOptions['paymentCurrencies'];
        $defaultPaymentCurrency = $currencyOptions['defaultPaymentCurrency'];

        $isNew = $request->boolean('new');
        $selected = $request->query('invoice_no');
        if ($isNew) {
            $selected = null;
        }
        if (! $isNew && ($selected === null || $selected === '')) {
            $selected = $invoiceNos->first()->invoice_no ?? null;
        }

        $order = null;
        if (! $isNew && $selected !== null && $selected !== '') {
            $order = $this->loadOrder((int) $selected);
        }

        return view('ecommerce.invoices', [
            'invoiceNos' => $invoiceNos,
            'selectedInvoiceNo' => $selected !== null && $selected !== '' ? (int) $selected : null,
            'order' => $order,
            'products' => $products,
            'clients' => $clients,
            'paymentCurrencies' => $paymentCurrencies,
            'defaultPaymentCurrency' => $defaultPaymentCurrency,
            'isNew' => $isNew,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_no' => ['required', 'integer'],
            'invoice_status' => ['required', Rule::in(self::INVOICE_STATUSES)],
            'invoice_memo' => ['nullable', 'string', 'max:100'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'recieve_amount' => ['required', 'numeric', 'min:0'],
            'payment_currency' => ['required'],
            'items' => ['nullable', 'array'],
            'items.*.product_no' => ['required_with:items', 'string', 'max:20'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1', 'max:9999'],
        ]);
        $recieveAmount = (float) ($validated['recieve_amount'] ?? 0);
        $currencyNo = $this->resolveCurrencyNoFromInput($validated['payment_currency']);

        $staff = StaffAuth::user();
        $employeeId = (int) ($staff['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return redirect()->route('staff.login')->with('error', 'Staff session expired. Please log in again.');
        }

        $clientNo = (int) $validated['client_no'];
        $clientExists = $this->db()->table('CLIENTS')
            ->where('CLIENT_NO', '=', $clientNo)
            ->exists();
        if (! $clientExists) {
            return back()->with('error', 'Selected client was not found.');
        }

        $items = collect($validated['items'] ?? [])
            ->map(static function (array $item): array {
                return [
                    'product_no' => trim((string) ($item['product_no'] ?? '')),
                    'qty' => (int) ($item['qty'] ?? 0),
                ];
            })
            ->filter(static fn (array $item): bool => $item['product_no'] !== '' && $item['qty'] > 0)
            ->values();

        if ($items->isNotEmpty()) {
            $upperProductNos = $items
                ->map(static fn (array $item): string => mb_strtoupper($item['product_no']))
                ->all();
            if (count($upperProductNos) !== count(array_unique($upperProductNos))) {
                return back()->withInput()->with('error', 'Duplicate product numbers were added.');
            }

            foreach ($items as $item) {
                $productNo = $item['product_no'];
                $product = $this->fetchProduct($productNo);
                if (! $product) {
                    return back()->withInput()->with('error', "Product {$productNo} was not found.");
                }

                $available = (int) $product->qty_on_hand;
                if ($item['qty'] > $available) {
                    return back()->withInput()->with('error', "Only {$available} unit(s) are available for product {$productNo}.");
                }
            }
        }

        try {
            $conn = $this->db();
            $invoiceNo = null;
            $conn->transaction(function () use ($conn, $clientNo, $employeeId, $validated, $items, $recieveAmount, $currencyNo, &$invoiceNo): void {
                $invoiceNo = $this->createInvoice(
                    $clientNo,
                    $employeeId,
                    (string) $validated['invoice_status'],
                    (string) ($validated['invoice_memo'] ?? '')
                );

                if ($items->isEmpty()) {
                    return;
                }

                foreach ($items as $item) {
                    $conn->insert(
                        'INSERT INTO INVOICE_DETAILS (INVOICE_NO, PRODUCT_NO, QTY) VALUES (:invoice_no, :product_no, :qty)',
                        [
                            'invoice_no' => $invoiceNo,
                            'product_no' => (string) $item['product_no'],
                            'qty' => (int) $item['qty'],
                        ]
                    );
                }

                $grandTotal = $this->invoiceGrandTotal($invoiceNo);
                $this->upsertPayment($invoiceNo, $grandTotal, $recieveAmount, $currencyNo);
            }, 3);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create invoice: '.$e->getMessage());
        }

        $qrAmountUsd = $this->toUsdAmount($recieveAmount, $currencyNo);
        $qrString = null;
        try {
            $qrString = BakongQR::generateMerchantQR($qrAmountUsd, (string) $invoiceNo);
        } catch (\Throwable $e) {
            // QR generation failure should not block invoice creation
        }

        $redirect = redirect()
            ->route('invoices.index', ['invoice_no' => $invoiceNo])
            ->with('success', "Invoice #{$invoiceNo} created.");

        if ($qrString) {
            $redirect->with('bakong_qr', $qrString)->with('bakong_qr_amount', $qrAmountUsd);
        }

        return $redirect;
    }

    public function addInvoiceItems(Request $request, int $invoiceNo): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_no' => ['required', 'string', 'max:20'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'recieve_amount' => ['required', 'numeric', 'min:0'],
            'payment_currency' => ['required'],
        ]);
        $recieveAmount = (float) ($validated['recieve_amount'] ?? 0);
        $currencyNo = $this->resolveCurrencyNoFromInput($validated['payment_currency']);

        $conn = $this->db();

        $invoiceExists = $conn->table('INVOICES')
            ->where('INVOICE_NO', '=', $invoiceNo)
            ->exists();
        if (! $invoiceExists) {
            return back()->with('error', "Invoice #{$invoiceNo} was not found.");
        }

        $items = collect($validated['items'] ?? [])
            ->map(static function (array $item): array {
                return [
                    'product_no' => trim((string) ($item['product_no'] ?? '')),
                    'qty' => (int) ($item['qty'] ?? 0),
                ];
            })
            ->filter(static fn (array $item): bool => $item['product_no'] !== '' && $item['qty'] > 0)
            ->values();

        if ($items->isEmpty()) {
            return back()->with('error', 'Add at least one item before saving.');
        }

        $upperProductNos = $items
            ->map(static fn (array $item): string => mb_strtoupper($item['product_no']))
            ->all();
        if (count($upperProductNos) !== count(array_unique($upperProductNos))) {
            return back()->withInput()->with('error', 'Duplicate product numbers were added.');
        }

        $existingProductNos = $conn->table('INVOICE_DETAILS')
            ->selectRaw('PRODUCT_NO as product_no')
            ->where('INVOICE_NO', '=', $invoiceNo)
            ->get()
            ->map(static fn (object $row): string => mb_strtoupper((string) ($row->product_no ?? $row->PRODUCT_NO ?? '')))
            ->filter()
            ->values()
            ->all();
        $existingLookup = array_flip($existingProductNos);

        foreach ($items as $item) {
            $productNo = $item['product_no'];
            if (isset($existingLookup[mb_strtoupper($productNo)])) {
                return back()->withInput()->with('error', "Product {$productNo} is already on this invoice.");
            }

            $product = $this->fetchProduct($productNo);
            if (! $product) {
                return back()->withInput()->with('error', "Product {$productNo} was not found.");
            }

            $available = (int) $product->qty_on_hand;
            if ($item['qty'] > $available) {
                return back()->withInput()->with('error', "Only {$available} unit(s) are available for product {$productNo}.");
            }
        }

        try {
            $conn->transaction(function () use ($conn, $invoiceNo, $items, $recieveAmount, $currencyNo): void {
                foreach ($items as $item) {
                    $conn->insert(
                        'INSERT INTO INVOICE_DETAILS (INVOICE_NO, PRODUCT_NO, QTY) VALUES (:invoice_no, :product_no, :qty)',
                        [
                            'invoice_no' => $invoiceNo,
                            'product_no' => (string) $item['product_no'],
                            'qty' => (int) $item['qty'],
                        ]
                    );
                }

                $grandTotal = $this->invoiceGrandTotal($invoiceNo);
                $this->upsertPayment($invoiceNo, $grandTotal, $recieveAmount, $currencyNo);
            }, 3);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Unable to add invoice items: '.$e->getMessage());
        }

        $qrAmountUsd = $this->toUsdAmount($recieveAmount, $currencyNo);
        $qrString = null;
        try {
            $qrString = BakongQR::generateMerchantQR($qrAmountUsd, (string) $invoiceNo);
        } catch (\Throwable $e) {
            // QR generation failure should not block invoice update
        }

        $redirect = redirect()
            ->route('invoices.index', ['invoice_no' => $invoiceNo])
            ->with('success', 'Invoice items added successfully.');

        if ($qrString) {
            $redirect->with('bakong_qr', $qrString)->with('bakong_qr_amount', $qrAmountUsd);
        }

        return $redirect;
    }

    public function addPurchaseItems(Request $request, int $purchaseNo): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_no' => ['required', 'string', 'max:20'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $conn = $this->db();
        $purchaseColumns = $this->tableColumns('PURCHASES');
        $detailColumns = $this->tableColumns('PURCHASE_DETAILS');

        $purchaseNoCol = $this->pickColumn($purchaseColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $statusCol = $this->pickColumn($purchaseColumns, ['PURCHASE_STATUS', 'STATUS']);
        if (! $purchaseNoCol) {
            return back()->with('error', 'Purchase schema is missing the purchase number column.');
        }

        $detailPurchaseCol = $this->pickColumn($detailColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $detailProductCol = $this->pickColumn($detailColumns, ['PRODUCT_NO', 'PRODUCT_ID']);
        $detailQtyCol = $this->pickColumn($detailColumns, ['QTY', 'QUANTITY']);
        $detailCostCol = $this->pickColumn($detailColumns, ['UNIT_COST', 'UNITCOST', 'UNIT_PRICE', 'PRICE', 'COST_PRICE', 'COST']);

        if (! $detailPurchaseCol || ! $detailProductCol || ! $detailQtyCol || ! $detailCostCol) {
            return back()->with('error', 'Purchase detail schema is missing required columns.');
        }

        $purchaseRow = $conn->table('PURCHASES')
            ->where($purchaseNoCol, '=', $purchaseNo)
            ->first();
        if (! $purchaseRow) {
            return back()->with('error', "Purchase #{$purchaseNo} was not found.");
        }
        $purchaseData = $this->normalizeRow($purchaseRow);
        $currentStatus = $statusCol ? mb_strtolower(trim((string) ($purchaseData[$statusCol] ?? ''))) : '';
        if ($statusCol && $currentStatus === 'completed') {
            return back()->with('error', "Can't Update. Purchase have Completed");
        }

        $items = collect($validated['items'] ?? [])
            ->map(static function (array $item): array {
                return [
                    'product_no' => trim((string) ($item['product_no'] ?? '')),
                    'qty' => (int) ($item['qty'] ?? 0),
                    'unit_cost' => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
                ];
            })
            ->filter(static fn (array $item): bool => $item['product_no'] !== '' && $item['qty'] > 0)
            ->values();

        if ($items->isEmpty()) {
            return back()->with('error', 'Add at least one item before saving.');
        }

        $upperProductNos = $items
            ->map(static fn (array $item): string => mb_strtoupper($item['product_no']))
            ->all();
        if (count($upperProductNos) !== count(array_unique($upperProductNos))) {
            return back()->withInput()->with('error', 'Duplicate product numbers were added.');
        }

        $existingProductNos = $conn->table('PURCHASE_DETAILS')
            ->selectRaw($detailProductCol.' as product_no')
            ->where($detailPurchaseCol, '=', $purchaseNo)
            ->get()
            ->map(static fn (object $row): string => mb_strtoupper((string) ($row->product_no ?? $row->PRODUCT_NO ?? '')))
            ->filter()
            ->values()
            ->all();
        $existingLookup = array_flip($existingProductNos);

        $productNos = $items->map(static fn (array $item): string => $item['product_no'])->all();
        $productCosts = $conn->table('PRODUCTS')
            ->selectRaw('PRODUCT_NO as product_no, COST_PRICE as cost_price')
            ->whereIn('PRODUCT_NO', $productNos)
            ->get()
            ->keyBy(static fn (object $row): string => (string) ($row->product_no ?? $row->PRODUCT_NO ?? ''));

        foreach ($items as &$item) {
            $productNo = $item['product_no'];
            if (isset($existingLookup[mb_strtoupper($productNo)])) {
                return back()->withInput()->with('error', "Product {$productNo} is already on this purchase.");
            }

            $product = $productCosts->get($productNo);
            if (! $product) {
                return back()->withInput()->with('error', "Product {$productNo} was not found.");
            }

            if ($item['unit_cost'] === null) {
                $item['unit_cost'] = (float) ($product->cost_price ?? 0);
            }
        }
        unset($item);

        try {
            $conn->transaction(function () use ($conn, $purchaseNo, $purchaseNoCol, $statusCol, $items, $detailPurchaseCol, $detailProductCol, $detailQtyCol, $detailCostCol): void {
                foreach ($items as $item) {
                    $conn->table('PURCHASE_DETAILS')->insert([
                        $detailPurchaseCol => $purchaseNo,
                        $detailProductCol => (string) $item['product_no'],
                        $detailQtyCol => (int) $item['qty'],
                        $detailCostCol => (float) $item['unit_cost'],
                    ]);
                }

                if ($statusCol) {
                    $conn->table('PURCHASES')
                        ->where($purchaseNoCol, '=', $purchaseNo)
                        ->update([$statusCol => 'Completed']);
                }
            }, 3);
        } catch (QueryException $e) {
            return back()->withInput()->with('error', 'Unable to add purchase items: '.$e->getMessage());
        }

        return redirect()
            ->route('purchases.index', ['purchase_no' => $purchaseNo])
            ->with('success', 'Purchase items added successfully.');
    }

    public function removeInvoiceItem(int $invoiceNo, string $productNo): RedirectResponse
    {
        $productNo = trim($productNo);
        if ($productNo === '') {
            return back()->with('error', 'Invalid product number.');
        }

        try {
            $deleted = $this->db()->table('INVOICE_DETAILS')
                ->where('INVOICE_NO', '=', $invoiceNo)
                ->where('PRODUCT_NO', '=', $productNo)
                ->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'Unable to delete invoice item: '.$e->getMessage());
        }

        if ($deleted === 0) {
            return back()->with('error', 'Invoice item was not found.');
        }

        return redirect()
            ->route('invoices.index', ['invoice_no' => $invoiceNo])
            ->with('success', 'Invoice item deleted.');
    }

    public function productPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_no' => ['required', 'string', 'max:20'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $productNo = trim((string) $validated['product_no']);
        $product = $this->fetchProduct($productNo);
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $qty = (int) ($validated['qty'] ?? 1);
        $price = (float) ($product->sell_price ?? 0);

        return response()->json([
            'product_no' => (string) $productNo,
            'price' => round($price, 2),
            'line_total' => round($price * $qty, 2),
            'qty_on_hand' => (int) ($product->qty_on_hand ?? 0),
            'available_stock' => (int) ($product->qty_on_hand ?? 0),
        ]);
    }

    public function productCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_no' => ['required', 'string', 'max:20'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $productNo = trim((string) $validated['product_no']);
        $product = $this->db()->table('PRODUCTS')
            ->selectRaw('PRODUCT_NO as product_no, COST_PRICE as cost_price')
            ->where('PRODUCT_NO', '=', $productNo)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $qty = (int) ($validated['qty'] ?? 1);
        $cost = (float) ($product->cost_price ?? 0);

        return response()->json([
            'product_no' => (string) $productNo,
            'unit_cost' => round($cost, 2),
            'line_total' => round($cost * $qty, 2),
        ]);
    }

    public function purchases(Request $request): View
    {
        $conn = $this->db();
        $purchaseColumns = $this->tableColumns('PURCHASES');
        $detailColumns = $this->tableColumns('PURCHASE_DETAILS');
        $supplierColumns = $this->tableColumns('SUPPLIERS');

        $purchaseNoCol = $this->pickColumn($purchaseColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        if (! $purchaseNoCol && $purchaseColumns !== []) {
            $purchaseNoCol = $purchaseColumns[0];
        }

        $purchaseNos = $purchaseNoCol
            ? $conn->table('PURCHASES')
                ->selectRaw($purchaseNoCol.' as purchase_no')
                ->orderByDesc($purchaseNoCol)
                ->limit(200)
                ->get()
            : collect();

        $isNew = $request->boolean('new');
        $selected = $request->query('purchase_no');
        if ($isNew) {
            $selected = null;
        }
        if (! $isNew && ($selected === null || $selected === '') && $purchaseNos->isNotEmpty()) {
            $selected = $purchaseNos->first()->purchase_no ?? null;
        }

        $selectedValue = $selected;
        if (is_numeric($selectedValue)) {
            $selectedValue = (int) $selectedValue;
        }

        $header = null;
        $supplier = null;
        $employee = null;
        $details = collect();
        $detailMode = 'structured';
        $detailHeaders = [];
        $subtotal = 0.0;
        $itemCount = 0;

        $purchaseDateCol = $this->pickColumn($purchaseColumns, ['PURCHASE_DATE', 'PUR_DATE', 'DATE', 'CREATED_AT']);
        $statusCol = $this->pickColumn($purchaseColumns, ['PURCHASE_STATUS', 'STATUS']);
        $memoCol = $this->pickColumn($purchaseColumns, ['PURCHASE_MEMO', 'MEMO', 'REMARKS', 'NOTE']);
        $supplierIdCol = $this->pickColumn($purchaseColumns, ['SUPPLIER_ID', 'SUPLIER_ID', 'SUPPLIER_NO', 'SUP_ID']);
        $employeeIdCol = $this->pickColumn($purchaseColumns, ['EMPLOYEE_ID', 'STAFF_ID']);
        $supplierKeyCol = $this->pickColumn($supplierColumns, ['SUPPLIER_ID', 'SUPPLIER_NO', 'SUP_ID']);
        $supplierNameCol = $this->pickColumn($supplierColumns, ['SUPPLIER_NAME', 'NAME', 'SUP_NAME']);
        $supplierPhoneCol = $this->pickColumn($supplierColumns, ['PHONE', 'TEL', 'PHONE_NO']);
        $supplierCityCol = $this->pickColumn($supplierColumns, ['CITY', 'COUNTRY_CITY', 'PROVINCE', 'TOWN', 'DISTRICT']);
        $supplierAddressCol = $this->pickColumn($supplierColumns, ['ADDRESS', 'ADDR']);
        $supplierEmailCol = $this->pickColumn($supplierColumns, ['EMAIL', 'EMAIL_ADDRESS', 'EMAILADDR']);

        $suppliers = collect();
        if ($supplierKeyCol && $supplierNameCol) {
            $supplierSelect = 's.'.$supplierKeyCol.' as supplier_id, s.'.$supplierNameCol.' as supplier_name';
            if ($supplierPhoneCol) {
                $supplierSelect .= ', s.'.$supplierPhoneCol.' as phone';
            } else {
                $supplierSelect .= ', NULL as phone';
            }
            if ($supplierCityCol) {
                $supplierSelect .= ', s.'.$supplierCityCol.' as city';
            } else {
                $supplierSelect .= ', NULL as city';
            }
            if ($supplierAddressCol) {
                $supplierSelect .= ', s.'.$supplierAddressCol.' as address';
            } else {
                $supplierSelect .= ', NULL as address';
            }
            if ($supplierEmailCol) {
                $supplierSelect .= ', s.'.$supplierEmailCol.' as email';
            } else {
                $supplierSelect .= ', NULL as email';
            }

            $suppliers = $conn->table('SUPPLIERS as s')
                ->selectRaw($supplierSelect)
                ->orderBy('s.'.$supplierNameCol)
                ->get();
        }

        $products = $conn->table('PRODUCTS')
            ->selectRaw('PRODUCT_NO as product_no, PRODUCT_NAME as product_name')
            ->orderBy('PRODUCT_NAME')
            ->get();

        $prefillItem = null;
        if ($isNew) {
            $prefillProductNo = trim((string) $request->query('prefill_product_no', ''));
            $prefillQtyRaw = $request->query('prefill_qty', 100);
            $prefillQty = is_numeric($prefillQtyRaw) ? (int) $prefillQtyRaw : 100;
            $prefillQty = max(1, min(9999, $prefillQty));

            if ($prefillProductNo !== '') {
                $prefillProduct = $conn->table('PRODUCTS')
                    ->selectRaw('PRODUCT_NO as product_no, PRODUCT_NAME as product_name, COST_PRICE as unit_cost')
                    ->where('PRODUCT_NO', '=', $prefillProductNo)
                    ->first();

                if ($prefillProduct) {
                    $prefillItem = (object) [
                        'product_no' => (string) ($prefillProduct->product_no ?? ''),
                        'product_name' => (string) ($prefillProduct->product_name ?? ''),
                        'qty' => $prefillQty,
                        'unit_cost' => round((float) ($prefillProduct->unit_cost ?? 0), 2),
                    ];
                }
            }
        }

        if (! $isNew && $purchaseNoCol && $selected !== null && $selected !== '') {
            $headerRow = $conn->table('PURCHASES')
                ->where($purchaseNoCol, '=', $selectedValue)
                ->first();
            $header = $this->normalizeRow($headerRow);

            if ($header && $supplierIdCol) {
                if ($supplierKeyCol && array_key_exists($supplierIdCol, $header)) {
                    $supplierRow = $conn->table('SUPPLIERS')
                        ->where($supplierKeyCol, '=', $header[$supplierIdCol])
                        ->first();
                    $supplier = $this->normalizeRow($supplierRow);
                }
            }

            if ($header && $employeeIdCol && array_key_exists($employeeIdCol, $header)) {
                $employeeRow = $conn->table('EMPLOYEES')
                    ->selectRaw('EMPLOYEE_ID as employee_id, EMPLOYEE_NAME as employee_name')
                    ->where('EMPLOYEE_ID', '=', $header[$employeeIdCol])
                    ->first();
                $employee = $this->normalizeRow($employeeRow);
            }

            $detailPurchaseCol = $this->pickColumn($detailColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
            $detailProductCol = $this->pickColumn($detailColumns, ['PRODUCT_NO', 'PRODUCT_ID']);
            $detailQtyCol = $this->pickColumn($detailColumns, ['QTY', 'QUANTITY']);
            $detailCostCol = $this->pickColumn($detailColumns, ['UNIT_COST', 'UNITCOST', 'UNIT_PRICE', 'PRICE', 'COST_PRICE', 'COST']);

            if ($detailPurchaseCol) {
                if ($detailProductCol && $detailQtyCol && $detailCostCol) {
                    $select = 'd.'.$detailProductCol.' as product_no,
                        d.'.$detailQtyCol.' as qty,
                        d.'.$detailCostCol.' as unit_cost,
                        (d.'.$detailQtyCol.' * d.'.$detailCostCol.') as amount';

                    $detailsQuery = $conn->table('PURCHASE_DETAILS as d')
                        ->where('d.'.$detailPurchaseCol, '=', $selectedValue);

                    if ($detailProductCol === 'PRODUCT_NO') {
                        $detailsQuery->leftJoin('PRODUCTS as p', 'p.PRODUCT_NO', '=', 'd.'.$detailProductCol);
                        $select = 'd.'.$detailProductCol.' as product_no,
                            p.PRODUCT_NAME as product_name,
                            d.'.$detailQtyCol.' as qty,
                            d.'.$detailCostCol.' as unit_cost,
                            (d.'.$detailQtyCol.' * d.'.$detailCostCol.') as amount';
                    }

                    $details = $detailsQuery
                        ->selectRaw($select)
                        ->orderBy('d.'.$detailProductCol)
                        ->get();

                    $subtotal = round((float) $details->sum('amount'), 2);
                    $itemCount = (int) $details->sum('qty');
                } else {
                    $detailMode = 'generic';
                    $details = $conn->table('PURCHASE_DETAILS')
                        ->where($detailPurchaseCol, '=', $selectedValue)
                        ->get();
                    $detailHeaders = $detailColumns;
                }
            }
        }

        $purchaseData = [
            'purchase_no' => $header ? $header[$purchaseNoCol] ?? null : null,
            'purchase_date' => $header && $purchaseDateCol ? ($header[$purchaseDateCol] ?? null) : null,
            'status' => $isNew ? 'In Process' : ($header && $statusCol ? ($header[$statusCol] ?? null) : null),
            'memo' => $header && $memoCol ? ($header[$memoCol] ?? null) : null,
            'supplier_id' => $header && $supplierIdCol ? ($header[$supplierIdCol] ?? null) : null,
            'employee_id' => $header && $employeeIdCol ? ($header[$employeeIdCol] ?? null) : null,
        ];

        return view('ecommerce.purchases', [
            'purchaseNos' => $purchaseNos,
            'selectedPurchaseNo' => $selected !== null && $selected !== '' ? $selectedValue : null,
            'purchaseData' => $purchaseData,
            'supplier' => $supplier,
            'suppliers' => $suppliers,
            'employee' => $employee,
            'products' => $products,
            'details' => $details,
            'detailMode' => $detailMode,
            'detailHeaders' => $detailHeaders,
            'subtotal' => $subtotal,
            'itemCount' => $itemCount,
            'isNew' => $isNew,
            'prefillItem' => $prefillItem,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function storePurchase(Request $request): RedirectResponse
    {
        $purchaseColumns = $this->tableColumns('PURCHASES');
        $detailColumns = $this->tableColumns('PURCHASE_DETAILS');
        $supplierColumns = $this->tableColumns('SUPPLIERS');

        $purchaseNoCol = $this->pickColumn($purchaseColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $purchaseDateCol = $this->pickColumn($purchaseColumns, ['PURCHASE_DATE', 'PUR_DATE', 'DATE', 'CREATED_AT']);
        $statusCol = $this->pickColumn($purchaseColumns, ['PURCHASE_STATUS', 'STATUS']);
        $memoCol = $this->pickColumn($purchaseColumns, ['PURCHASE_MEMO', 'MEMO', 'REMARKS', 'NOTE']);
        $supplierIdCol = $this->pickColumn($purchaseColumns, ['SUPPLIER_ID', 'SUPLIER_ID', 'SUPPLIER_NO', 'SUP_ID']);
        $employeeIdCol = $this->pickColumn($purchaseColumns, ['EMPLOYEE_ID', 'STAFF_ID']);
        $supplierKeyCol = $this->pickColumn($supplierColumns, ['SUPPLIER_ID', 'SUPPLIER_NO', 'SUP_ID']);
        $detailPurchaseCol = $this->pickColumn($detailColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $detailProductCol = $this->pickColumn($detailColumns, ['PRODUCT_NO', 'PRODUCT_ID']);
        $detailQtyCol = $this->pickColumn($detailColumns, ['QTY', 'QUANTITY']);
        $detailCostCol = $this->pickColumn($detailColumns, ['UNIT_COST', 'UNITCOST', 'UNIT_PRICE', 'PRICE', 'COST_PRICE', 'COST']);

        if (! $supplierIdCol || ! $employeeIdCol || ! $supplierKeyCol) {
            return back()->with('error', 'Purchase schema is missing required columns.');
        }

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'purchase_memo' => ['nullable', 'string', 'max:100'],
            'prefill_product_no' => ['nullable', 'string', 'max:20'],
            'prefill_qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'items' => ['nullable', 'array'],
            'items.*.product_no' => ['required_with:items', 'string', 'max:20'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $staff = StaffAuth::user();
        $employeeId = (int) ($staff['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return redirect()->route('staff.login')->with('error', 'Staff session expired. Please log in again.');
        }

        $supplierId = (int) $validated['supplier_id'];
        $supplierExists = $this->db()->table('SUPPLIERS')
            ->where($supplierKeyCol, '=', $supplierId)
            ->exists();
        if (! $supplierExists) {
            return back()->with('error', 'Selected supplier was not found.');
        }

        $insertData = [
            $supplierIdCol => $supplierId,
            $employeeIdCol => $employeeId,
        ];

        if ($purchaseDateCol) {
            $insertData[$purchaseDateCol] = now();
        }
        if ($statusCol) {
            $insertData[$statusCol] = 'In Process';
        }
        if ($memoCol) {
            $memo = trim((string) ($validated['purchase_memo'] ?? ''));
            $insertData[$memoCol] = $memo !== '' ? $memo : null;
        }

        $items = collect($validated['items'] ?? [])
            ->map(static function (array $item): array {
                return [
                    'product_no' => trim((string) ($item['product_no'] ?? '')),
                    'qty' => (int) ($item['qty'] ?? 0),
                    'unit_cost' => isset($item['unit_cost']) && $item['unit_cost'] !== '' ? (float) $item['unit_cost'] : null,
                ];
            })
            ->filter(static fn (array $item): bool => $item['product_no'] !== '' && $item['qty'] > 0)
            ->values();

        $prefillProductNo = trim((string) ($validated['prefill_product_no'] ?? ''));
        $prefillQty = (int) ($validated['prefill_qty'] ?? 100);
        if ($prefillProductNo !== '') {
            $items = $items->push([
                'product_no' => $prefillProductNo,
                'qty' => $prefillQty > 0 ? $prefillQty : 1,
                'unit_cost' => null,
            ]);
        }

        if ($items->isNotEmpty()) {
            if (! $purchaseNoCol || ! $detailPurchaseCol || ! $detailProductCol || ! $detailQtyCol || ! $detailCostCol) {
                return back()->with('error', 'Purchase detail schema is missing required columns.');
            }

            $upperProductNos = $items
                ->map(static fn (array $item): string => mb_strtoupper($item['product_no']))
                ->all();
            if (count($upperProductNos) !== count(array_unique($upperProductNos))) {
                return back()->withInput()->with('error', 'Duplicate product numbers were added.');
            }

            $productNos = $items->map(static fn (array $item): string => $item['product_no'])->all();
            $productCosts = $this->db()->table('PRODUCTS')
                ->selectRaw('PRODUCT_NO as product_no, COST_PRICE as cost_price')
                ->whereIn('PRODUCT_NO', $productNos)
                ->get()
                ->keyBy(static fn (object $row): string => (string) ($row->product_no ?? $row->PRODUCT_NO ?? ''));

            foreach ($items as &$item) {
                $productNo = $item['product_no'];
                $product = $productCosts->get($productNo);
                if (! $product) {
                    return back()->withInput()->with('error', "Product {$productNo} was not found.");
                }

                if ($item['unit_cost'] === null) {
                    $item['unit_cost'] = (float) ($product->cost_price ?? 0);
                }
            }
            unset($item);
        }

        $newPurchaseNo = null;
        try {
            $conn = $this->db();
            $conn->transaction(function () use ($conn, $insertData, $purchaseNoCol, $supplierIdCol, $employeeIdCol, $supplierId, $employeeId, $items, $detailPurchaseCol, $detailProductCol, $detailQtyCol, $detailCostCol, $statusCol, &$newPurchaseNo): void {
                $conn->table('PURCHASES')->insert($insertData);

                if ($purchaseNoCol) {
                    try {
                        $sequence = $this->identitySequence('PURCHASES', $purchaseNoCol);
                        $newPurchaseNo = $this->currentSequenceValue($sequence);
                    } catch (RuntimeException) {
                        $latest = $conn->table('PURCHASES')
                            ->where($supplierIdCol, '=', $supplierId)
                            ->where($employeeIdCol, '=', $employeeId)
                            ->orderByDesc($purchaseNoCol)
                            ->first([$purchaseNoCol.' as purchase_no']);
                        $newPurchaseNo = $latest->purchase_no ?? null;
                    }
                }

                if ($items->isEmpty()) {
                    return;
                }

                if (! $newPurchaseNo) {
                    throw new RuntimeException('Unable to resolve purchase number for detail insert.');
                }

                foreach ($items as $item) {
                    $conn->table('PURCHASE_DETAILS')->insert([
                        $detailPurchaseCol => $newPurchaseNo,
                        $detailProductCol => (string) $item['product_no'],
                        $detailQtyCol => (int) $item['qty'],
                        $detailCostCol => (float) ($item['unit_cost'] ?? 0),
                    ]);
                }

                if ($statusCol) {
                    $conn->table('PURCHASES')
                        ->where($purchaseNoCol, '=', $newPurchaseNo)
                        ->update([$statusCol => 'Completed']);
                }
            }, 3);
        } catch (QueryException $e) {
            return back()->withInput()->with('error', 'Failed to create purchase: '.$e->getMessage());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', 'Failed to create purchase: '.$e->getMessage());
        }

        if ($newPurchaseNo !== null && $newPurchaseNo !== '') {
            $message = "Purchase #{$newPurchaseNo} created.";
            if ($items->isNotEmpty()) {
                $message = "Purchase #{$newPurchaseNo} created with items.";
            }

            return redirect()
                ->route('purchases.index', ['purchase_no' => $newPurchaseNo])
                ->with('success', $message);
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Purchase created.');
    }

    public function showOrder(Request $request, int $invoiceNo): RedirectResponse|View
    {
        $order = $this->loadOrder($invoiceNo);
        if (! $order) {
            return redirect()->route('store.orders')->with('error', "Invoice #{$invoiceNo} was not found.");
        }
        $debtAmount = $this->resolveInvoiceDebtAmount($invoiceNo);
        $autoPrint = $request->boolean('autoprint');

        if ($request->boolean('print')) {
            return view('ecommerce.order-receipt', [
                'order' => $order,
                'printedAt' => now(),
                'debtAmount' => $debtAmount,
                'autoPrint' => $autoPrint,
            ]);
        }

        $currencyOptions = $this->paymentCurrencyOptions();

        return view('ecommerce.order-show', [
            'order' => $order,
            'debtAmount' => $debtAmount,
            'paymentCurrencies' => $currencyOptions['paymentCurrencies'],
            'defaultPaymentCurrency' => $currencyOptions['defaultPaymentCurrency'],
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    public function completeOrder(int $invoiceNo): RedirectResponse
    {
        if (! StaffAuth::can('orders.manage')) {
            return back()->with('error', 'You do not have permission to complete orders.');
        }

        $debtAmount = $this->resolveInvoiceDebtAmount($invoiceNo);
        $nextStatus = $debtAmount > 0 ? 'In Debt' : 'Completed';

        try {
            $updated = $this->db()->table('INVOICES')
                ->where('INVOICE_NO', '=', $invoiceNo)
                ->update(['INVOICE_STATUS' => $nextStatus]);
        } catch (QueryException $e) {
            return back()->with('error', 'Failed to complete invoice: '.$e->getMessage());
        }

        if ($updated === 0) {
            return back()->with('error', "Invoice #{$invoiceNo} was not found.");
        }

        return redirect()
            ->route('store.orders.show', ['invoiceNo' => $invoiceNo, 'print' => 1])
            ->with('success', $nextStatus === 'In Debt'
                ? 'Invoice has remaining debt and was marked as In Debt.'
                : 'Arrange Goods Completed');
    }

    public function repayOrder(Request $request, int $invoiceNo): RedirectResponse
    {
        if (! StaffAuth::can('client-depts.manage')) {
            return back()->with('error', 'You do not have permission to repay invoices.');
        }

        $validated = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'recieve_amount' => ['required', 'numeric', 'min:0'],
            'payment_currency' => ['required'],
        ]);

        $invoiceExists = $this->db()->table('INVOICES')
            ->where('INVOICE_NO', '=', $invoiceNo)
            ->exists();
        if (! $invoiceExists) {
            return back()->with('error', "Invoice #{$invoiceNo} was not found.");
        }

        $currentDebt = round(max(0, $this->resolveInvoiceDebtAmount($invoiceNo)), 2);
        if ($currentDebt <= 0) {
            $this->db()->table('INVOICES')
                ->where('INVOICE_NO', '=', $invoiceNo)
                ->update(['INVOICE_STATUS' => 'Completed']);

            return redirect()
                ->route('store.orders.show', ['invoiceNo' => $invoiceNo, 'print' => 1, 'autoprint' => 1])
                ->with('success', 'No remaining debt. Invoice marked as Completed.');
        }

        $resolvedCurrencyNo = $this->resolveCurrencyNoFromInput($validated['payment_currency']);
        if ($resolvedCurrencyNo === null || $resolvedCurrencyNo <= 0) {
            $resolvedCurrencyNo = $this->defaultCurrencyNo();
        }
        $rateToUsd = $resolvedCurrencyNo !== null && $resolvedCurrencyNo > 0
            ? $this->currencyRateToUsd($resolvedCurrencyNo)
            : 1.0;
        if ($rateToUsd <= 0) {
            $rateToUsd = 1.0;
        }

        $recieveAmount = round(max(0, (float) $validated['recieve_amount']), 2);
        $recieveAmountUsd = round(max(0, $recieveAmount / $rateToUsd), 2);
        $paymentAmount = $currentDebt;
        $remainingDebt = round(max(0, $paymentAmount - $recieveAmountUsd), 2);
        $nextStatus = $remainingDebt > 0 ? 'In Debt' : 'Completed';

        try {
            $this->db()->transaction(function () use ($invoiceNo, $paymentAmount, $recieveAmount, $recieveAmountUsd, $rateToUsd, $nextStatus, $resolvedCurrencyNo): void {
                try {
                    $this->insertPaymentRecord($invoiceNo, $paymentAmount, $recieveAmount, $resolvedCurrencyNo);
                } catch (QueryException $e) {
                    // Some schemas enforce one PAYMENTS row per invoice (unique INVOICE_NO).
                    if (! str_contains(mb_strtoupper($e->getMessage()), 'ORA-00001')) {
                        throw $e;
                    }

                    $alreadyReceivedUsd = $this->resolveInvoiceReceivedAmount($invoiceNo);
                    $totalReceivedUsd = round(max(0, $alreadyReceivedUsd + $recieveAmountUsd), 2);
                    $totalReceivedInSelectedCurrency = round(max(0, $totalReceivedUsd * $rateToUsd), 2);
                    $this->upsertPayment(
                        $invoiceNo,
                        $paymentAmount,
                        $totalReceivedInSelectedCurrency,
                        $resolvedCurrencyNo
                    );
                }

                $this->db()->table('INVOICES')
                    ->where('INVOICE_NO', '=', $invoiceNo)
                    ->update(['INVOICE_STATUS' => $nextStatus]);
            }, 3);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Unable to save repayment: '.$e->getMessage());
        }

        if ($nextStatus === 'Completed') {
            return redirect()
                ->route('store.orders.show', ['invoiceNo' => $invoiceNo, 'print' => 1, 'autoprint' => 1])
                ->with('success', 'Repayment saved. Invoice is fully paid and marked Completed.');
        }

        return redirect()
            ->route('store.orders.show', ['invoiceNo' => $invoiceNo, 'print' => 1, 'autoprint' => 1])
            ->with('success', 'Repayment saved. Invoice is still In Debt.');
    }

    public function deepCheck(): View
    {
        $conn = $this->db();

        $tables = $conn->select("
            SELECT
                t.table_name,
                t.num_rows,
                TO_CHAR(t.last_analyzed, 'YYYY-MM-DD HH24:MI:SS') AS last_analyzed,
                (SELECT COUNT(*) FROM user_tab_columns c WHERE c.table_name = t.table_name) AS column_count
            FROM user_tables t
            ORDER BY t.table_name
        ");

        $views = $conn->select('SELECT view_name FROM user_views ORDER BY view_name');
        $triggers = $conn->select('
            SELECT trigger_name, table_name, triggering_event, status
            FROM user_triggers
            ORDER BY trigger_name
        ');
        $identities = $conn->select('
            SELECT table_name, column_name, sequence_name
            FROM user_tab_identity_cols
            ORDER BY table_name
        ');

        return view('ecommerce.deep-check', [
            'tables' => $tables,
            'views' => $views,
            'triggers' => $triggers,
            'identities' => $identities,
            'cartCount' => $this->cart->totalQuantity(),
            'inProcessCount' => $this->countInProcessInvoices(),
        ]);
    }

    private function insertPurchasePrefillItem(int $purchaseNo, string $productNo, int $qty): void
    {
        $detailColumns = $this->tableColumns('PURCHASE_DETAILS');
        $detailPurchaseCol = $this->pickColumn($detailColumns, ['PURCHASE_NO', 'PURCHASE_ID', 'PUR_NO']);
        $detailProductCol = $this->pickColumn($detailColumns, ['PRODUCT_NO', 'PRODUCT_ID']);
        $detailQtyCol = $this->pickColumn($detailColumns, ['QTY', 'QUANTITY']);
        $detailCostCol = $this->pickColumn($detailColumns, ['UNIT_COST', 'UNITCOST', 'UNIT_PRICE', 'PRICE', 'COST_PRICE', 'COST']);

        if (! $detailPurchaseCol || ! $detailProductCol || ! $detailQtyCol || ! $detailCostCol) {
            throw new RuntimeException('Purchase detail schema is missing required columns.');
        }

        $product = $this->db()->table('PRODUCTS')
            ->selectRaw('PRODUCT_NO as product_no, COST_PRICE as cost_price')
            ->where('PRODUCT_NO', '=', $productNo)
            ->first();

        if (! $product) {
            throw new RuntimeException("Product {$productNo} was not found.");
        }

        try {
            $this->db()->table('PURCHASE_DETAILS')->insert([
                $detailPurchaseCol => $purchaseNo,
                $detailProductCol => $productNo,
                $detailQtyCol => max(1, min(9999, $qty)),
                $detailCostCol => (float) ($product->cost_price ?? 0),
            ]);
        } catch (QueryException $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    private function countInProcessInvoices(): int
    {
        return (int) $this->db()->table('INVOICES')
            ->where('INVOICE_STATUS', '=', 'In Process')
            ->count();
    }

    private function db()
    {
        return DB::connection(self::ORACLE_CONNECTION);
    }

    private function fetchProduct(string $productNo): ?object
    {
        return $this->db()->table('PRODUCTS as p')
            ->leftJoin('PRODUCT_TYPE as t', 't.PRODUCTTYPE_ID', '=', 'p.PRODUCT_TYPE')
            ->selectRaw('
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                p.SELL_PRICE as sell_price,
                p.QTY_ON_HAND as qty_on_hand,
                p.UNIT_MEASURE as unit_measure,
                t.PRODUCTYPE_NAME as product_type_name
            ')
            ->where('p.PRODUCT_NO', '=', $productNo)
            ->first();
    }

    private function cartItemsWithLiveData(): Collection
    {
        $sessionItems = $this->cart->raw();
        if ($sessionItems === []) {
            return collect();
        }

        $productNos = array_keys($sessionItems);
        $products = $this->db()->table('PRODUCTS as p')
            ->leftJoin('PRODUCT_TYPE as t', 't.PRODUCTTYPE_ID', '=', 'p.PRODUCT_TYPE')
            ->selectRaw('
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                p.SELL_PRICE as sell_price,
                p.QTY_ON_HAND as qty_on_hand,
                p.UNIT_MEASURE as unit_measure,
                p.STATUS as stock_status,
                t.PRODUCTYPE_NAME as product_type_name
            ')
            ->whereIn('p.PRODUCT_NO', $productNos)
            ->get()
            ->keyBy(fn ($row): string => (string) $row->product_no);

        return collect($sessionItems)
            ->map(function (array $item, string $productNo) use ($products): array {
                $product = $products->get($productNo);
                $exists = $product !== null;
                $qty = (int) ($item['qty'] ?? 0);
                $available = $exists ? (int) $product->qty_on_hand : 0;
                $price = $exists ? (float) $product->sell_price : (float) ($item['sell_price'] ?? 0);

                return [
                    'product_no' => $productNo,
                    'product_name' => (string) ($product->product_name ?? $item['product_name'] ?? 'Unknown product'),
                    'product_type_name' => (string) ($product->product_type_name ?? $item['product_type_name'] ?? ''),
                    'unit_measure' => (string) ($product->unit_measure ?? $item['unit_measure'] ?? ''),
                    'stock_status' => (string) ($product->stock_status ?? ''),
                    'qty' => $qty,
                    'available_stock' => $available,
                    'sell_price' => $price,
                    'line_total' => round($qty * $price, 2),
                    'exists' => $exists,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, float|int>
     */
    private function calculateTotals(Collection $items): array
    {
        $subtotal = round((float) $items->sum('line_total'), 2);
        $itemsCount = (int) $items->sum('qty');

        return [
            'items' => $itemsCount,
            'subtotal' => $subtotal,
        ];
    }

    private function resolveClientType(mixed $clientType): ?object
    {
        if (! is_numeric($clientType)) {
            return null;
        }

        return $this->db()->table('CLIENT_TYPE')
            ->selectRaw('CLIENTTYPE_ID as clienttype_id, TYPE_NAME as type_name, DISCOUNT_RATE as discount_rate')
            ->where('CLIENTTYPE_ID', '=', (int) $clientType)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertClient(array $payload, ?object $clientType): int
    {
        $conn = $this->db();
        $phone = trim((string) ($payload['phone'] ?? ''));
        $name = trim((string) ($payload['customer_name'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));

        $existing = $conn->table('CLIENTS')
            ->selectRaw('CLIENT_NO as client_no, CLIENT_TYPE as client_type, DISCOUNT as discount')
            ->where('PHONE', '=', $phone)
            ->first();

        $chosenTypeId = (int) ($clientType->clienttype_id ?? $existing->client_type ?? 0);
        if ($chosenTypeId <= 0) {
            $chosenTypeId = $this->defaultClientTypeId();
        }

        $discount = (float) ($clientType->discount_rate ?? $existing->discount ?? 0);
        if ($existing) {
            $finalName = $this->uniqueClientName($name, $phone, (int) $existing->client_no);
            $conn->table('CLIENTS')
                ->where('CLIENT_NO', '=', (int) $existing->client_no)
                ->update([
                    'CLIENT_NAME' => $finalName,
                    'ADDRESS' => $address !== '' ? $address : null,
                    'CITY' => $city !== '' ? $city : null,
                    'CLIENT_TYPE' => $chosenTypeId,
                    'DISCOUNT' => $discount,
                ]);

            return (int) $existing->client_no;
        }

        $finalName = $this->uniqueClientName($name, $phone, null);
        $conn->insert(
            'INSERT INTO CLIENTS (CLIENT_NAME, ADDRESS, CITY, PHONE, CLIENT_TYPE, DISCOUNT)
             VALUES (:client_name, :address, :city, :phone, :client_type, :discount)',
            [
                'client_name' => $finalName,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'phone' => $phone,
                'client_type' => $chosenTypeId,
                'discount' => $discount,
            ]
        );

        $sequence = $this->identitySequence('CLIENTS', 'CLIENT_NO');

        return $this->currentSequenceValue($sequence);
    }

    private function createInvoice(int $clientNo, int $employeeId, string $status, string $memo): int
    {
        $this->db()->insert(
            'INSERT INTO INVOICES (CLIENT_NO, EMPLOYEE_ID, INVOICE_STATUS, INVOICE_MEMO)
             VALUES (:client_no, :employee_id, :invoice_status, :invoice_memo)',
            [
                'client_no' => $clientNo,
                'employee_id' => $employeeId,
                'invoice_status' => $status,
                'invoice_memo' => $memo !== '' ? $memo : null,
            ]
        );

        $sequence = $this->identitySequence('INVOICES', 'INVOICE_NO');

        return $this->currentSequenceValue($sequence);
    }

    private function identitySequence(string $tableName, string $columnName): string
    {
        $key = strtoupper($tableName).'.'.strtoupper($columnName);
        if (isset($this->identitySequences[$key])) {
            return $this->identitySequences[$key];
        }

        $row = $this->db()->selectOne(
            'SELECT SEQUENCE_NAME FROM USER_TAB_IDENTITY_COLS WHERE TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            [
                'table_name' => strtoupper($tableName),
                'column_name' => strtoupper($columnName),
            ]
        );

        $sequence = $row->sequence_name ?? $row->SEQUENCE_NAME ?? null;
        if (! is_string($sequence) || $sequence === '') {
            throw new RuntimeException("Identity sequence not found for {$key}.");
        }

        $this->identitySequences[$key] = $sequence;

        return $sequence;
    }

    private function currentSequenceValue(string $sequence): int
    {
        $row = $this->db()->selectOne('SELECT '.$sequence.'.CURRVAL AS ID FROM DUAL');
        $value = $row->id ?? $row->ID ?? null;

        return (int) $value;
    }

    private function defaultClientTypeId(): int
    {
        $row = $this->db()->table('CLIENT_TYPE')
            ->selectRaw('CLIENTTYPE_ID as clienttype_id, TYPE_NAME as type_name')
            ->orderByRaw("CASE WHEN UPPER(TYPE_NAME) = 'NORMAL' THEN 0 ELSE 1 END")
            ->orderBy('CLIENTTYPE_ID')
            ->first();

        return (int) ($row->clienttype_id ?? $row->CLIENTTYPE_ID ?? 0);
    }

    private function uniqueClientName(string $name, string $phone, ?int $exceptClientNo): string
    {
        $name = trim($name) !== '' ? trim($name) : "Customer {$phone}";
        $query = $this->db()->table('CLIENTS')->where('CLIENT_NAME', '=', $name);
        if ($exceptClientNo) {
            $query->where('CLIENT_NO', '!=', $exceptClientNo);
        }

        if (! $query->exists()) {
            return mb_substr($name, 0, 50);
        }

        $fallback = mb_substr($name.' ('.$phone.')', 0, 50);
        $fallbackQuery = $this->db()->table('CLIENTS')->where('CLIENT_NAME', '=', $fallback);
        if ($exceptClientNo) {
            $fallbackQuery->where('CLIENT_NO', '!=', $exceptClientNo);
        }

        if (! $fallbackQuery->exists()) {
            return $fallback;
        }

        return mb_substr($name.' '.now()->format('His'), 0, 50);
    }

    private function buildOrderQuery(string $q, string $status)
    {
        $query = $this->db()->table('INVOICES as i')
            ->join('CLIENTS as c', 'c.CLIENT_NO', '=', 'i.CLIENT_NO')
            ->join('EMPLOYEES as e', 'e.EMPLOYEE_ID', '=', 'i.EMPLOYEE_ID')
            ->leftJoin('INVOICE_DETAILS as d', 'd.INVOICE_NO', '=', 'i.INVOICE_NO')
            ->selectRaw('
                i.INVOICE_NO as invoice_no,
                i.INVOICE_DATE as invoice_date,
                c.CLIENT_NAME as client_name,
                c.PHONE as phone,
                e.EMPLOYEE_NAME as seller,
                i.INVOICE_STATUS as invoice_status,
                NVL(SUM(d.QTY * d.PRICE), 0) as subtotal,
                NVL(SUM(d.QTY), 0) as item_qty
            ')
            ->groupBy(
                'i.INVOICE_NO',
                'i.INVOICE_DATE',
                'c.CLIENT_NAME',
                'c.PHONE',
                'e.EMPLOYEE_NAME',
                'i.INVOICE_STATUS'
            );

        if ($status !== '') {
            $query->where('i.INVOICE_STATUS', '=', $status);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $upper = '%'.mb_strtoupper($q).'%';

                if (ctype_digit($q)) {
                    $sub->where('i.INVOICE_NO', '=', (int) $q)
                        ->orWhereRaw('c.PHONE LIKE ?', ['%'.$q.'%'])
                        ->orWhereRaw('UPPER(c.CLIENT_NAME) LIKE ?', [$upper]);
                } else {
                    $sub->whereRaw('UPPER(c.CLIENT_NAME) LIKE ?', [$upper]);
                }
            });
        }

        return $query;
    }

    private function loadOrder(int $invoiceNo): ?array
    {
        $conn = $this->db();

        $header = $conn->table('INVOICES as i')
            ->join('CLIENTS as c', 'c.CLIENT_NO', '=', 'i.CLIENT_NO')
            ->join('EMPLOYEES as e', 'e.EMPLOYEE_ID', '=', 'i.EMPLOYEE_ID')
            ->selectRaw('
                i.INVOICE_NO as invoice_no,
                i.INVOICE_DATE as invoice_date,
                i.INVOICE_STATUS as invoice_status,
                i.INVOICE_MEMO as invoice_memo,
                c.CLIENT_NO as client_no,
                c.CLIENT_NAME as client_name,
                c.PHONE as phone,
                c.ADDRESS as address,
                c.CITY as city,
                c.DISCOUNT as client_discount,
                e.EMPLOYEE_ID as employee_id,
                e.EMPLOYEE_NAME as employee_name
            ')
            ->where('i.INVOICE_NO', '=', $invoiceNo)
            ->first();

        if (! $header) {
            return null;
        }

        $items = $conn->table('INVOICE_DETAILS as d')
            ->join('PRODUCTS as p', 'p.PRODUCT_NO', '=', 'd.PRODUCT_NO')
            ->selectRaw('
                d.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                d.QTY as qty,
                d.PRICE as price,
                (d.QTY * d.PRICE) as line_total
            ')
            ->where('d.INVOICE_NO', '=', $invoiceNo)
            ->orderBy('d.PRODUCT_NO')
            ->get();

        $subtotal = round((float) $items->sum('line_total'), 2);
        $discountRate = $this->normalizedDiscountRate((float) ($header->client_discount ?? 0));
        $discountAmount = round($subtotal * $discountRate, 2);
        $grandTotal = round(max(0, $subtotal - $discountAmount), 2);

        return [
            'header' => $header,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'grand_total' => $grandTotal,
            'item_count' => (int) $items->sum('qty'),
        ];
    }

    private function invoiceGrandTotal(int $invoiceNo): float
    {
        $order = $this->loadOrder($invoiceNo);
        if (! $order) {
            throw new RuntimeException("Invoice #{$invoiceNo} was not found.");
        }

        return round((float) ($order['grand_total'] ?? 0), 2);
    }

    private function upsertPayment(int $invoiceNo, float $amount, float $recieveAmount, ?int $currencyNo = null): void
    {
        $columns = $this->tableColumns('PAYMENTS');
        $invoiceCol = $this->pickColumn($columns, ['INVOICE_NO']);
        $amountCol = $this->pickColumn($columns, ['AMOUNT']);
        $recieveCol = $this->pickColumn($columns, ['RECIEVE_AMOUNT', 'RECEIVE_AMOUNT']);
        $usdCol = $this->pickColumn($columns, ['USD']);
        $currencyNoCol = $this->pickColumn($columns, ['CURRENCY_NO']);
        $createAtCol = $this->pickColumn($columns, ['CREATE_AT', 'CREATED_AT']);
        $updateAtCol = $this->pickColumn($columns, ['UPDATE_AT', 'UPDATED_AT']);

        if (! $invoiceCol || ! $amountCol) {
            throw new RuntimeException('PAYMENTS schema is missing required columns.');
        }

        $resolvedCurrencyNo = $currencyNo !== null && $currencyNo > 0
            ? $currencyNo
            : $this->defaultCurrencyNo();
        if ($currencyNoCol && ($resolvedCurrencyNo === null || $resolvedCurrencyNo <= 0)) {
            throw new RuntimeException('PAYMENTS schema requires CURRENCY_NO, but no default currency was found.');
        }

        $rateToUsd = $resolvedCurrencyNo !== null ? $this->currencyRateToUsd($resolvedCurrencyNo) : 1.0;
        if ($rateToUsd <= 0) {
            $rateToUsd = 1.0;
        }

        $finalInvoiceAmount = round(max(0, $amount), 2);
        $finalRecieveRaw = round(max(0, $recieveAmount), 2);
        $finalRecieveUsd = round(max(0, $finalRecieveRaw / $rateToUsd), 2);
        $finalInvoiceAmountLocal = round(max(0, $finalInvoiceAmount * $rateToUsd), 2);
        $invoiceKey = (string) $invoiceNo;
        $now = now();
        $conn = $this->db();

        $basePayload = [$invoiceCol => $invoiceKey];
        if ($recieveCol) {
            if ($currencyNoCol) {
                // Trigger-driven schema: AMOUNT/RECIEVE_AMOUNT are in selected currency.
                $basePayload[$amountCol] = $finalInvoiceAmountLocal;
                $basePayload[$recieveCol] = $finalRecieveRaw;
                // Do not set USD/DEBT_AMOUNT here; triggers populate them.
            } else {
                $basePayload[$amountCol] = $finalInvoiceAmount;
                $basePayload[$recieveCol] = $finalRecieveUsd;
                if ($usdCol) {
                    $basePayload[$usdCol] = $finalRecieveUsd;
                }
            }
        } else {
            // Trigger-driven schema: AMOUNT + CURRENCY_NO -> USD.
            $basePayload[$amountCol] = $finalRecieveRaw;
            if ($usdCol && ! $currencyNoCol) {
                $basePayload[$usdCol] = $finalRecieveUsd;
            }
        }
        if ($currencyNoCol && $resolvedCurrencyNo !== null) {
            $basePayload[$currencyNoCol] = $resolvedCurrencyNo;
        }

        try {
            $exists = $conn->table('PAYMENTS')
                ->where($invoiceCol, '=', $invoiceKey)
                ->exists();

            if ($exists) {
                $updatePayload = $basePayload;
                if ($updateAtCol) {
                    $updatePayload[$updateAtCol] = $now;
                }

                $conn->table('PAYMENTS')
                    ->where($invoiceCol, '=', $invoiceKey)
                    ->update($updatePayload);

                return;
            }

            $insertPayload = $basePayload;
            if ($createAtCol) {
                $insertPayload[$createAtCol] = $now;
            }
            if ($updateAtCol) {
                $insertPayload[$updateAtCol] = $now;
            }

            $conn->table('PAYMENTS')->insert($insertPayload);
        } catch (QueryException $e) {
            if (stripos($e->getMessage(), 'PAYMENTS_FK2') !== false) {
                throw new RuntimeException(
                    'Unable to save PAYMENTS due to PAYMENTS_FK2 schema mismatch. '.
                    'Fix FK to reference PAYMENTS.INVOICE_NO -> INVOICES.INVOICE_NO.'
                );
            }

            throw $e;
        }
    }

    private function insertPaymentRecord(int $invoiceNo, float $amount, float $recieveAmount, ?int $currencyNo = null): void
    {
        $columns = $this->tableColumns('PAYMENTS');
        $invoiceCol = $this->pickColumn($columns, ['INVOICE_NO']);
        $amountCol = $this->pickColumn($columns, ['AMOUNT']);
        $recieveCol = $this->pickColumn($columns, ['RECIEVE_AMOUNT', 'RECEIVE_AMOUNT']);
        $usdCol = $this->pickColumn($columns, ['USD']);
        $currencyNoCol = $this->pickColumn($columns, ['CURRENCY_NO']);
        $createAtCol = $this->pickColumn($columns, ['CREATE_AT', 'CREATED_AT']);
        $updateAtCol = $this->pickColumn($columns, ['UPDATE_AT', 'UPDATED_AT']);

        if (! $invoiceCol || ! $amountCol) {
            throw new RuntimeException('PAYMENTS schema is missing required columns.');
        }

        $resolvedCurrencyNo = $currencyNo !== null && $currencyNo > 0
            ? $currencyNo
            : $this->defaultCurrencyNo();
        if ($currencyNoCol && ($resolvedCurrencyNo === null || $resolvedCurrencyNo <= 0)) {
            throw new RuntimeException('PAYMENTS schema requires CURRENCY_NO, but no default currency was found.');
        }

        $rateToUsd = $resolvedCurrencyNo !== null ? $this->currencyRateToUsd($resolvedCurrencyNo) : 1.0;
        if ($rateToUsd <= 0) {
            $rateToUsd = 1.0;
        }

        $finalInvoiceAmount = round(max(0, $amount), 2);
        $finalRecieveRaw = round(max(0, $recieveAmount), 2);
        $finalRecieveUsd = round(max(0, $finalRecieveRaw / $rateToUsd), 2);
        $finalInvoiceAmountLocal = round(max(0, $finalInvoiceAmount * $rateToUsd), 2);

        $payload = [$invoiceCol => (string) $invoiceNo];
        if ($recieveCol) {
            if ($currencyNoCol) {
                // Trigger-driven schema: AMOUNT/RECIEVE_AMOUNT are in selected currency.
                $payload[$amountCol] = $finalInvoiceAmountLocal;
                $payload[$recieveCol] = $finalRecieveRaw;
                // Do not set USD/DEBT_AMOUNT here; triggers populate them.
            } else {
                $payload[$amountCol] = $finalInvoiceAmount;
                $payload[$recieveCol] = $finalRecieveUsd;
                if ($usdCol) {
                    $payload[$usdCol] = $finalRecieveUsd;
                }
            }
        } else {
            $payload[$amountCol] = $finalRecieveRaw;
            if ($usdCol && ! $currencyNoCol) {
                $payload[$usdCol] = $finalRecieveUsd;
            }
        }
        if ($currencyNoCol && $resolvedCurrencyNo !== null) {
            $payload[$currencyNoCol] = $resolvedCurrencyNo;
        }

        if ($createAtCol) {
            $payload[$createAtCol] = now();
        }
        if ($updateAtCol) {
            $payload[$updateAtCol] = now();
        }

        $this->db()->table('PAYMENTS')->insert($payload);
    }

    private function resolveInvoiceDebtAmount(int $invoiceNo): float
    {
        $columns = $this->tableColumns('PAYMENTS');
        $invoiceCol = $this->pickColumn($columns, ['INVOICE_NO']);

        if (! $invoiceCol) {
            return 0.0;
        }

        try {
            $grandTotal = $this->invoiceGrandTotal($invoiceNo);
        } catch (\Throwable) {
            $grandTotal = 0.0;
        }

        $totalRecieved = $this->resolveInvoiceReceivedAmount($invoiceNo);

        return round(max(0, $grandTotal - $totalRecieved), 2);
    }

    private function resolveInvoiceReceivedAmount(int $invoiceNo): float
    {
        $columns = $this->tableColumns('PAYMENTS');
        $invoiceCol = $this->pickColumn($columns, ['INVOICE_NO']);
        $currencyNoCol = $this->pickColumn($columns, ['CURRENCY_NO']);
        $recieveCol = $this->pickColumn($columns, ['RECIEVE_AMOUNT', 'RECEIVE_AMOUNT']);
        $usdCol = $this->pickColumn($columns, ['USD']);
        if (! $invoiceCol) {
            return 0.0;
        }

        if ($recieveCol) {
            if ($currencyNoCol) {
                $rows = $this->db()->table('PAYMENTS')
                    ->where($invoiceCol, '=', (string) $invoiceNo)
                    ->get([
                        $recieveCol.' as recieve_amount',
                        $currencyNoCol.' as currency_no',
                    ]);

                $totalUsd = 0.0;
                foreach ($rows as $row) {
                    $recieveRaw = $row->recieve_amount ?? $row->RECIEVE_AMOUNT ?? null;
                    if (! is_numeric($recieveRaw)) {
                        continue;
                    }

                    $currencyNo = (int) ($row->currency_no ?? $row->CURRENCY_NO ?? 0);
                    $rateToUsd = $currencyNo > 0 ? $this->currencyRateToUsd($currencyNo) : 1.0;
                    if ($rateToUsd <= 0) {
                        $rateToUsd = 1.0;
                    }

                    $totalUsd += max(0, (float) $recieveRaw) / $rateToUsd;
                }

                return round(max(0, $totalUsd), 2);
            }

            $sumRecieved = $this->db()->table('PAYMENTS')
                ->where($invoiceCol, '=', (string) $invoiceNo)
                ->selectRaw('NVL(SUM('.$recieveCol.'), 0) as total_recieve')
                ->value('total_recieve');
            if (is_numeric($sumRecieved)) {
                return round(max(0, (float) $sumRecieved), 2);
            }
        }

        if ($usdCol) {
            $sumUsd = $this->db()->table('PAYMENTS')
                ->where($invoiceCol, '=', (string) $invoiceNo)
                ->selectRaw('NVL(SUM('.$usdCol.'), 0) as total_recieve')
                ->value('total_recieve');
            if (is_numeric($sumUsd)) {
                return round(max(0, (float) $sumUsd), 2);
            }
        }

        return 0.0;
    }

    /**
     * @return array{paymentCurrencies: Collection<int, array<string, mixed>>, defaultPaymentCurrency: string}
     */
    private function paymentCurrencyOptions(): array
    {
        $paymentCurrencies = collect();
        $defaultPaymentCurrency = (string) config('bakong.default_currency', 'USD');

        try {
            $currencyColumns = $this->tableColumns('CURRENCIES');
            $currencyIdCol = $this->pickColumn($currencyColumns, ['CURRENCY_NO', 'CURRENCY_ID', 'ID']);
            $currencyNameCol = $this->pickColumn($currencyColumns, ['CURRENCY_NAME', 'NAME']);
            $currencyCodeCol = $this->pickColumn($currencyColumns, ['CURRENCY_CODE', 'CODE', 'ISO_CODE']);
            $currencyRateCol = $this->pickColumn($currencyColumns, [
                'EXCHANGE_RATE_TO_USD',
                'EXCHANGE_RATE',
                'RATE',
                'RATE_TO_USD',
                'USD_RATE',
            ]);

            if ($currencyNameCol || $currencyCodeCol || $currencyIdCol || $currencyRateCol) {
                $currencySelect = [];
                if ($currencyIdCol) {
                    $currencySelect[] = 'c.'.$currencyIdCol.' as currency_id';
                }
                if ($currencyNameCol) {
                    $currencySelect[] = 'c.'.$currencyNameCol.' as currency_name';
                }
                if ($currencyCodeCol) {
                    $currencySelect[] = 'c.'.$currencyCodeCol.' as currency_code';
                }
                if ($currencyRateCol) {
                    $currencySelect[] = 'c.'.$currencyRateCol.' as exchange_rate_to_usd';
                }

                $query = $this->db()->table('CURRENCIES as c')->selectRaw(implode(', ', $currencySelect));
                if ($currencyNameCol) {
                    $query->orderBy('c.'.$currencyNameCol);
                } elseif ($currencyCodeCol) {
                    $query->orderBy('c.'.$currencyCodeCol);
                } elseif ($currencyIdCol) {
                    $query->orderBy('c.'.$currencyIdCol);
                }

                $paymentCurrencies = $query->get()
                    ->map(static function (object $row): array {
                        $id = trim((string) ($row->currency_id ?? $row->CURRENCY_ID ?? ''));
                        $name = trim((string) ($row->currency_name ?? $row->CURRENCY_NAME ?? ''));
                        $code = trim((string) ($row->currency_code ?? $row->CURRENCY_CODE ?? ''));
                        $rateRaw = $row->exchange_rate_to_usd ?? $row->EXCHANGE_RATE_TO_USD ?? 1;
                        $rate = is_numeric($rateRaw) ? (float) $rateRaw : 1.0;
                        if ($rate <= 0) {
                            $rate = 1.0;
                        }

                        $value = $id !== '' ? $id : ($code !== '' ? $code : $name);
                        $label = $name !== ''
                            ? ($code !== '' ? "{$name} ({$code})" : $name)
                            : ($code !== '' ? $code : ($id !== '' ? "#{$id}" : 'Unknown'));

                        return [
                            'value' => $value,
                            'label' => $label,
                            'code' => $code !== '' ? $code : ($name !== '' ? $name : 'USD'),
                            'rate_to_usd' => round($rate, 6),
                        ];
                    })
                    ->filter(static fn (array $item): bool => trim((string) ($item['value'] ?? '')) !== '')
                    ->unique(static fn (array $item): string => mb_strtoupper((string) ($item['value'] ?? '')))
                    ->values();

                $preferred = mb_strtoupper(trim($defaultPaymentCurrency));
                $preferredCurrency = $paymentCurrencies->first(static function (array $item) use ($preferred): bool {
                    $code = mb_strtoupper(trim((string) ($item['code'] ?? '')));
                    $label = mb_strtoupper(trim((string) ($item['label'] ?? '')));

                    return $code === $preferred || str_contains($label, $preferred);
                });

                if ($preferredCurrency) {
                    $defaultPaymentCurrency = (string) ($preferredCurrency['value'] ?? $defaultPaymentCurrency);
                } else {
                    $firstCurrency = $paymentCurrencies->first();
                    if (is_array($firstCurrency) && isset($firstCurrency['value'])) {
                        $defaultPaymentCurrency = (string) $firstCurrency['value'];
                    }
                }
            }
        } catch (\Throwable) {
            // Keep fallback default currency when CURRENCIES is unavailable.
        }

        return [
            'paymentCurrencies' => $paymentCurrencies,
            'defaultPaymentCurrency' => $defaultPaymentCurrency,
        ];
    }

    private function resolveCurrencyNoFromInput(mixed $input): ?int
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $numeric = (int) $raw;
            if ($numeric > 0) {
                return $numeric;
            }
        }

        $columns = $this->tableColumns('CURRENCIES');
        $idCol = $this->pickColumn($columns, ['CURRENCY_NO', 'CURRENCY_ID', 'ID']);
        $codeCol = $this->pickColumn($columns, ['CURRENCY_CODE', 'CODE', 'ISO_CODE']);
        $nameCol = $this->pickColumn($columns, ['CURRENCY_NAME', 'NAME']);
        if (! $idCol || (! $codeCol && ! $nameCol)) {
            return null;
        }

        $lookup = mb_strtoupper($raw);
        $row = $this->db()->table('CURRENCIES')
            ->selectRaw($idCol.' as currency_no')
            ->where(function ($query) use ($lookup, $codeCol, $nameCol): void {
                if ($codeCol) {
                    $query->whereRaw('UPPER(TRIM('.$codeCol.')) = ?', [$lookup]);
                }
                if ($nameCol) {
                    if ($codeCol) {
                        $query->orWhereRaw('UPPER(TRIM('.$nameCol.')) = ?', [$lookup]);
                    } else {
                        $query->whereRaw('UPPER(TRIM('.$nameCol.')) = ?', [$lookup]);
                    }
                }
            })
            ->first();

        $resolved = (int) ($row->currency_no ?? $row->CURRENCY_NO ?? 0);

        return $resolved > 0 ? $resolved : null;
    }

    private function defaultCurrencyNo(): ?int
    {
        $columns = $this->tableColumns('CURRENCIES');
        $idCol = $this->pickColumn($columns, ['CURRENCY_NO', 'CURRENCY_ID', 'ID']);
        $codeCol = $this->pickColumn($columns, ['CURRENCY_CODE', 'CODE', 'ISO_CODE']);
        $nameCol = $this->pickColumn($columns, ['CURRENCY_NAME', 'NAME']);
        $rateCol = $this->pickColumn($columns, ['EXCHANGE_RATE_TO_USD', 'EXCHANGE_RATE', 'RATE', 'RATE_TO_USD', 'USD_RATE']);

        if (! $idCol) {
            return null;
        }

        $conn = $this->db();

        if ($codeCol) {
            $row = $conn->table('CURRENCIES')
                ->selectRaw($idCol.' as currency_no')
                ->whereRaw('UPPER('.$codeCol.") = 'USD'")
                ->first();
            $id = (int) ($row->currency_no ?? $row->CURRENCY_NO ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        if ($nameCol) {
            $row = $conn->table('CURRENCIES')
                ->selectRaw($idCol.' as currency_no')
                ->whereRaw(
                    'UPPER('.$nameCol.") IN ('US DOLLAR', 'USD', 'DOLLAR')"
                )
                ->first();
            $id = (int) ($row->currency_no ?? $row->CURRENCY_NO ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        if ($rateCol) {
            $row = $conn->table('CURRENCIES')
                ->selectRaw($idCol.' as currency_no')
                ->whereRaw('ABS(NVL('.$rateCol.', 0) - 1) < 0.0001')
                ->orderBy($idCol)
                ->first();
            $id = (int) ($row->currency_no ?? $row->CURRENCY_NO ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $fallback = $conn->table('CURRENCIES')
            ->selectRaw($idCol.' as currency_no')
            ->orderBy($idCol)
            ->first();
        $id = (int) ($fallback->currency_no ?? $fallback->CURRENCY_NO ?? 0);

        return $id > 0 ? $id : null;
    }

    private function currencyRateToUsd(int $currencyNo): float
    {
        if ($currencyNo <= 0) {
            return 1.0;
        }

        $columns = $this->tableColumns('CURRENCIES');
        $idCol = $this->pickColumn($columns, ['CURRENCY_NO', 'CURRENCY_ID', 'ID']);
        $rateCol = $this->pickColumn($columns, ['EXCHANGE_RATE_TO_USD', 'EXCHANGE_RATE', 'RATE', 'RATE_TO_USD', 'USD_RATE']);
        if (! $idCol || ! $rateCol) {
            return 1.0;
        }

        $rate = $this->db()->table('CURRENCIES')
            ->where($idCol, '=', $currencyNo)
            ->value($rateCol);
        if (! is_numeric($rate)) {
            return 1.0;
        }

        $resolved = (float) $rate;

        return $resolved > 0 ? $resolved : 1.0;
    }

    private function toUsdAmount(float $amount, ?int $currencyNo = null): float
    {
        $resolvedCurrencyNo = $currencyNo !== null && $currencyNo > 0
            ? $currencyNo
            : $this->defaultCurrencyNo();
        $rateToUsd = $resolvedCurrencyNo !== null ? $this->currencyRateToUsd($resolvedCurrencyNo) : 1.0;
        if ($rateToUsd <= 0) {
            $rateToUsd = 1.0;
        }

        return round(max(0, $amount) / $rateToUsd, 2);
    }

    private function normalizedDiscountRate(float $discount): float
    {
        if ($discount <= 0) {
            return 0.0;
        }

        if ($discount >= 10) {
            return $discount / 100;
        }

        if ($discount >= 1) {
            return $discount / 10;
        }

        return $discount;
    }

    /**
     * @return array<string, string>
     */
    private function purchaseHistorySortOptions(): array
    {
        return [
            'latest' => 'Newest first',
            'oldest' => 'Oldest first',
            'supplier' => 'Supplier A-Z',
            'total_desc' => 'Total high-low',
            'total_asc' => 'Total low-high',
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyPurchaseHistorySort($query, string $sort, string $purchaseNoCol, string $purchaseDateCol): void
    {
        if ($sort === 'oldest') {
            $query->orderBy('p.'.$purchaseDateCol, 'asc')
                ->orderBy('p.'.$purchaseNoCol, 'asc');

            return;
        }

        if ($sort === 'supplier') {
            $query->orderBy('supplier_name', 'asc')
                ->orderBy('p.'.$purchaseNoCol, 'desc');

            return;
        }

        if ($sort === 'total_desc') {
            $query->orderBy('subtotal', 'desc')
                ->orderBy('p.'.$purchaseNoCol, 'desc');

            return;
        }

        if ($sort === 'total_asc') {
            $query->orderBy('subtotal', 'asc')
                ->orderBy('p.'.$purchaseNoCol, 'asc');

            return;
        }

        $query->orderBy('p.'.$purchaseDateCol, 'desc')
            ->orderBy('p.'.$purchaseNoCol, 'desc');
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        $rows = $this->db()->table('USER_TAB_COLUMNS')
            ->selectRaw('COLUMN_NAME as column_name')
            ->where('TABLE_NAME', '=', mb_strtoupper($table))
            ->orderBy('COLUMN_ID')
            ->get();

        return $rows
            ->map(static fn (object $row): string => mb_strtoupper((string) ($row->column_name ?? $row->COLUMN_NAME ?? '')))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $candidates
     */
    private function pickColumn(array $columns, array $candidates): ?string
    {
        $normalizedColumns = [];
        foreach ($columns as $column) {
            $normalizedColumns[$this->normalizeColumnToken($column)] = $column;
        }

        foreach ($candidates as $candidate) {
            $upper = mb_strtoupper($candidate);
            if (in_array($upper, $columns, true)) {
                return $upper;
            }

            $normalized = $this->normalizeColumnToken($candidate);
            if (isset($normalizedColumns[$normalized])) {
                return $normalizedColumns[$normalized];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRow(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        $normalized = [];
        foreach ((array) $row as $key => $value) {
            $normalized[mb_strtoupper((string) $key)] = $value;
        }

        return $normalized;
    }

    private function normalizeColumnToken(string $name): string
    {
        $upper = mb_strtoupper(trim($name));
        $token = preg_replace('/[^A-Z0-9]+/', '_', $upper);

        return trim((string) $token, '_');
    }
}
