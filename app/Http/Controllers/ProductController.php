<?php

namespace App\Http\Controllers;

use App\Support\StaffAuth;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $conn = DB::connection('oracle');
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));

        $select = '
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                p.PRODUCT_TYPE as product_type_id,
                t.PRODUCTYPE_NAME as product_type_name,
                p.SELL_PRICE as sell_price,
                p.COST_PRICE as cost_price,
                p.PROFIT_PERCENT as profit_percent,
                p.UNIT_MEASURE as unit_measure,
                p.QTY_ON_HAND as qty_on_hand,
                p.STATUS as stock_status,
                (SELECT CASE WHEN pp2.MEDIA IS NOT NULL AND DBMS_LOB.GETLENGTH(pp2.MEDIA) <= 2000
                    THEN UTL_RAW.CAST_TO_VARCHAR2(DBMS_LOB.SUBSTR(pp2.MEDIA, 2000, 1))
                    ELSE NULL END
                 FROM PRODUCT_PHOTO pp2
                 WHERE pp2.PRODUCT_ID = p.PRODUCT_NO
                   AND pp2.PHOTO_ID = (SELECT MIN(pp3.PHOTO_ID) FROM PRODUCT_PHOTO pp3 WHERE pp3.PRODUCT_ID = p.PRODUCT_NO)
                ) as photo_path';

        $productsQuery = $conn->table('PRODUCTS as p')
            ->leftJoin('PRODUCT_TYPE as t', 't.PRODUCTTYPE_ID', '=', 'p.PRODUCT_TYPE')
            ->selectRaw($select);

        if ($q !== '') {
            $keyword = '%'.mb_strtoupper($q).'%';
            $productsQuery->where(function ($sub) use ($keyword, $q): void {
                $sub->whereRaw('UPPER(p.PRODUCT_NAME) LIKE ?', [$keyword])
                    ->orWhereRaw('UPPER(p.PRODUCT_NO) LIKE ?', [$keyword])
                    ->orWhereRaw('UPPER(t.PRODUCTYPE_NAME) LIKE ?', [$keyword])
                    ->orWhereRaw('p.PRODUCT_NO LIKE ?', ['%'.$q.'%']);
            });
        }

        if ($type !== '' && ctype_digit($type)) {
            $productsQuery->where('p.PRODUCT_TYPE', '=', (int) $type);
        }

        $products = $productsQuery
            ->orderBy('p.PRODUCT_NAME')
            ->paginate(15)
            ->appends($request->query());

        $types = $conn->table('PRODUCT_TYPE')
            ->selectRaw('PRODUCTTYPE_ID as id, PRODUCTYPE_NAME as name')
            ->orderBy('PRODUCTYPE_NAME')
            ->get();

        $metrics = [
            'products' => (int) $conn->table('PRODUCTS')->count(),
            'types' => (int) $conn->table('PRODUCT_TYPE')->count(),
            'understock' => (int) $conn->table('PRODUCTS')
                ->whereRaw("UPPER(NVL(STATUS, 'UNKNOWN')) = ?", ['UNDERSTOCK'])
                ->count(),
        ];

        $alertStocks = $conn->table('ALERT_STOCKS as a')
            ->join('PRODUCTS as p', 'p.PRODUCT_NO', '=', 'a.PRODUCT_NO')
            ->leftJoin('PRODUCT_TYPE as t', 't.PRODUCTTYPE_ID', '=', 'p.PRODUCT_TYPE')
            ->selectRaw('
                a.ALERT_STOCK_NO as alert_stock_no,
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                p.PRODUCT_TYPE as product_type_id,
                t.PRODUCTYPE_NAME as product_type_name,
                p.SELL_PRICE as sell_price,
                p.COST_PRICE as cost_price,
                p.PROFIT_PERCENT as profit_percent,
                p.UNIT_MEASURE as unit_measure,
                p.QTY_ON_HAND as qty_on_hand,
                a.LOWER_QTY as lower_qty,
                a.HIGHER_QTY as higher_qty,
                p.STATUS as stock_status,
                (SELECT CASE WHEN pp2.MEDIA IS NOT NULL AND DBMS_LOB.GETLENGTH(pp2.MEDIA) <= 2000
                    THEN UTL_RAW.CAST_TO_VARCHAR2(DBMS_LOB.SUBSTR(pp2.MEDIA, 2000, 1))
                    ELSE NULL END
                 FROM PRODUCT_PHOTO pp2
                 WHERE pp2.PRODUCT_ID = p.PRODUCT_NO
                   AND pp2.PHOTO_ID = (SELECT MIN(pp3.PHOTO_ID) FROM PRODUCT_PHOTO pp3 WHERE pp3.PRODUCT_ID = p.PRODUCT_NO)
                ) as photo_path
            ')
            ->orderBy('p.QTY_ON_HAND')
            ->get();

        return view('products.index', [
            'products' => $products,
            'types' => $types,
            'metrics' => $metrics,
            'alertStocks' => $alertStocks,
            'q' => $q,
            'type' => $type,
            'canManageProducts' => StaffAuth::can('products.manage'),
            'canManageTypes' => StaffAuth::can('product-types.manage'),
            'canManageStockStatus' => StaffAuth::can('stock-status.manage'),
            'underStockCount' => (int) $metrics['understock'],
        ]);
    }

    public function status(Request $request): View
    {
        $conn = DB::connection('oracle');
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $sort = trim((string) $request->query('sort', 'qty_asc'));
        $sortOptions = [
            'qty_asc' => 'Qty: Low to High',
            'qty_desc' => 'Qty: High to Low',
            'name_asc' => 'Name: A-Z',
            'name_desc' => 'Name: Z-A',
            'gap_desc' => 'Gap: High to Low',
            'gap_asc' => 'Gap: Low to High',
        ];
        if (! array_key_exists($sort, $sortOptions)) {
            $sort = 'qty_asc';
        }

        $productColumns = $conn->table('USER_TAB_COLUMNS')
            ->selectRaw('COLUMN_NAME as column_name, DATA_TYPE as data_type')
            ->where('TABLE_NAME', '=', 'PRODUCTS')
            ->get()
            ->mapWithKeys(static function (object $row): array {
                $name = mb_strtoupper((string) ($row->column_name ?? $row->COLUMN_NAME ?? ''));
                $dataType = mb_strtoupper((string) ($row->data_type ?? $row->DATA_TYPE ?? ''));

                return $name !== '' ? [$name => $dataType] : [];
            });

        $photoColumn = null;
        $photoType = null;
        foreach (['PHOTO', 'IMAGE', 'PRODUCT_PHOTO', 'PRODUCT_IMAGE', 'PHOTO_PATH', 'IMAGE_PATH'] as $candidate) {
            $upper = mb_strtoupper($candidate);
            if ($productColumns->has($upper)) {
                $photoColumn = $upper;
                $photoType = $productColumns->get($upper);
                break;
            }
        }

        $photoSelect = 'NULL as photo_path';
        if ($photoColumn) {
            if ($photoType === 'BLOB') {
                $photoSelect = 'CASE WHEN DBMS_LOB.GETLENGTH(p.'.$photoColumn.') <= 2000
                    THEN UTL_RAW.CAST_TO_VARCHAR2(DBMS_LOB.SUBSTR(p.'.$photoColumn.', 2000, 1))
                    ELSE NULL END as photo_path';
            } elseif ($photoType === 'CLOB') {
                $photoSelect = 'DBMS_LOB.SUBSTR(p.'.$photoColumn.', 4000, 1) as photo_path';
            } else {
                $photoSelect = 'p.'.$photoColumn.' as photo_path';
            }
        }

        $query = $conn->table('PRODUCTS as p')
            ->leftJoin('PRODUCT_TYPE as t', 't.PRODUCTTYPE_ID', '=', 'p.PRODUCT_TYPE')
            ->leftJoin('ALERT_STOCKS as a', 'a.PRODUCT_NO', '=', 'p.PRODUCT_NO')
            ->selectRaw('
                p.PRODUCT_NO as product_no,
                p.PRODUCT_NAME as product_name,
                t.PRODUCTYPE_NAME as product_type_name,
                p.QTY_ON_HAND as qty_on_hand,
                p.STATUS as stock_status,
                a.LOWER_QTY as lower_qty,
                a.HIGHER_QTY as higher_qty,
                CASE
                    WHEN UPPER(NVL(p.STATUS, \'ENOUGH\')) = \'UNDERSTOCK\' THEN \'UNDERSTOCK\'
                    WHEN UPPER(NVL(p.STATUS, \'ENOUGH\')) = \'OVERSTOCK\' THEN \'OVERSTOCK\'
                    ELSE \'ENOUGH\'
                END as status_group,
                '.$photoSelect.'
            ');

        if ($q !== '') {
            $keyword = '%'.mb_strtoupper($q).'%';
            $query->where(function ($sub) use ($keyword, $q): void {
                $sub->whereRaw('UPPER(p.PRODUCT_NAME) LIKE ?', [$keyword])
                    ->orWhereRaw('UPPER(p.PRODUCT_NO) LIKE ?', [$keyword])
                    ->orWhereRaw('UPPER(t.PRODUCTYPE_NAME) LIKE ?', [$keyword])
                    ->orWhereRaw('p.PRODUCT_NO LIKE ?', ['%'.$q.'%']);
            });
        }

        if ($type !== '' && ctype_digit($type)) {
            $query->where('p.PRODUCT_TYPE', '=', (int) $type);
        }

        if ($sort === 'qty_desc') {
            $query->orderByDesc('p.QTY_ON_HAND')->orderBy('p.PRODUCT_NAME');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('p.PRODUCT_NAME');
        } elseif ($sort === 'name_desc') {
            $query->orderByDesc('p.PRODUCT_NAME');
        } elseif ($sort === 'gap_desc') {
            $query->orderByRaw('ABS(NVL(a.LOWER_QTY, 0) - NVL(p.QTY_ON_HAND, 0)) DESC')->orderBy('p.PRODUCT_NAME');
        } elseif ($sort === 'gap_asc') {
            $query->orderByRaw('ABS(NVL(a.LOWER_QTY, 0) - NVL(p.QTY_ON_HAND, 0)) ASC')->orderBy('p.PRODUCT_NAME');
        } else {
            $query->orderBy('p.QTY_ON_HAND')->orderBy('p.PRODUCT_NAME');
        }

        $rows = $query->get();

        $underStockRows = $rows->filter(static fn (object $row): bool => mb_strtoupper((string) ($row->status_group ?? '')) === 'UNDERSTOCK')->values();
        $enoughRows = $rows->filter(static fn (object $row): bool => mb_strtoupper((string) ($row->status_group ?? '')) === 'ENOUGH')->values();
        $overStockRows = $rows->filter(static fn (object $row): bool => mb_strtoupper((string) ($row->status_group ?? '')) === 'OVERSTOCK')->values();

        $statusCounts = [
            'understock' => $underStockRows->count(),
            'enough' => $enoughRows->count(),
            'overstock' => $overStockRows->count(),
        ];

        $statusQtyTotals = [
            'understock' => (float) $underStockRows->sum(static fn (object $row): float => (float) ($row->qty_on_hand ?? 0)),
            'enough' => (float) $enoughRows->sum(static fn (object $row): float => (float) ($row->qty_on_hand ?? 0)),
            'overstock' => (float) $overStockRows->sum(static fn (object $row): float => (float) ($row->qty_on_hand ?? 0)),
        ];

        $underGapRows = $underStockRows
            ->map(static function (object $row): object {
                $row->gap_value = max(0, (float) ($row->lower_qty ?? 0) - (float) ($row->qty_on_hand ?? 0));

                return $row;
            })
            ->sortByDesc(static fn (object $row): float => (float) ($row->gap_value ?? 0))
            ->values()
            ->take(8);

        $overGapRows = $overStockRows
            ->map(static function (object $row): object {
                $row->gap_value = max(0, (float) ($row->qty_on_hand ?? 0) - (float) ($row->higher_qty ?? 0));

                return $row;
            })
            ->sortByDesc(static fn (object $row): float => (float) ($row->gap_value ?? 0))
            ->values()
            ->take(8);

        $allGapRows = $rows
            ->map(static function (object $row): object {
                $statusGroup = mb_strtoupper((string) ($row->status_group ?? 'ENOUGH'));
                $qty = (float) ($row->qty_on_hand ?? 0);
                $lower = (float) ($row->lower_qty ?? 0);
                $higher = (float) ($row->higher_qty ?? 0);

                if ($statusGroup === 'UNDERSTOCK') {
                    $row->gap_value = max(0, $lower - $qty);
                } elseif ($statusGroup === 'OVERSTOCK') {
                    $row->gap_value = max(0, $qty - $higher);
                } else {
                    $row->gap_value = 0.0;
                }

                return $row;
            })
            ->sortByDesc(static fn (object $row): float => (float) ($row->qty_on_hand ?? 0))
            ->values();

        $types = $conn->table('PRODUCT_TYPE')
            ->selectRaw('PRODUCTTYPE_ID as id, PRODUCTYPE_NAME as name')
            ->orderBy('PRODUCTYPE_NAME')
            ->get();

        return view('products.status', [
            'q' => $q,
            'type' => $type,
            'sort' => $sort,
            'sortOptions' => $sortOptions,
            'types' => $types,
            'underStockRows' => $underStockRows,
            'enoughRows' => $enoughRows,
            'overStockRows' => $overStockRows,
            'statusCounts' => $statusCounts,
            'statusQtyTotals' => $statusQtyTotals,
            'underGapRows' => $underGapRows,
            'overGapRows' => $overGapRows,
            'allGapRows' => $allGapRows,
            'underStockCount' => (int) $statusCounts['understock'],
        ]);
    }

    public function future(Request $request): View
    {
        $conn = DB::connection('oracle');
        $mode = mb_strtolower(trim((string) $request->query('mode', 'monthly')));
        if (! in_array($mode, ['monthly', 'yearly'], true)) {
            $mode = 'monthly';
        }

        $q = trim((string) $request->query('q', ''));
        $selectedProductNo = trim((string) $request->query('product_no', ''));
        $selectedMonth = trim((string) $request->query('month', ''));
        $selectedYear = trim((string) $request->query('year', ''));

        try {
            $conn->statement("ALTER SESSION SET NLS_DATE_FORMAT = 'DD-MON-RR'");
        } catch (\Throwable) {
            // If ALTER SESSION is blocked, continue; query can still work in many environments.
        }

        $futureError = null;
        try {
            $allRows = collect($conn->select("
                SELECT
                    TO_CHAR(f.FORECAST_MONTH, 'YYYY-MM') AS forecast_month,
                    TO_CHAR(f.FORECAST_MONTH, 'YYYY') AS forecast_year,
                    f.PRODUCT_NO AS product_no,
                    f.PRODUCT_NAME AS product_name,
                    NVL(f.FORECAST_UNITS, 0) AS forecast_units
                FROM FUTURE_PRODUCTS f
                ORDER BY f.FORECAST_MONTH ASC, f.PRODUCT_NO ASC
            "))->map(static function (object $row): object {
                $row->forecast_month = (string) ($row->forecast_month ?? '');
                $row->forecast_year = (string) ($row->forecast_year ?? '');
                $row->product_no = (string) ($row->product_no ?? '');
                $row->product_name = (string) ($row->product_name ?? '');
                $row->forecast_units = (float) ($row->forecast_units ?? 0);

                return $row;
            })->values();
        } catch (QueryException $e) {
            $allRows = collect();
            $futureError = 'Unable to read Oracle view FUTURE_PRODUCTS: '.$e->getMessage();
        }

        $monthOptions = $allRows
            ->pluck('forecast_month')
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->sortDesc()
            ->values();

        $yearOptions = $allRows
            ->pluck('forecast_year')
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->sortDesc()
            ->values();

        $productOptions = $allRows
            ->groupBy('product_no')
            ->map(static function ($rows, string $productNo): object {
                $first = $rows->first();

                return (object) [
                    'product_no' => $productNo,
                    'product_name' => (string) ($first->product_name ?? $productNo),
                ];
            })
            ->values()
            ->sortBy(static fn (object $row): string => $row->product_name)
            ->values();

        if ($selectedMonth === '' && $monthOptions->isNotEmpty()) {
            $selectedMonth = (string) $monthOptions->first();
        }
        if ($selectedYear === '' && $yearOptions->isNotEmpty()) {
            $selectedYear = (string) $yearOptions->first();
        }

        $filteredRows = $allRows;
        if ($selectedProductNo !== '') {
            $filteredRows = $filteredRows
                ->filter(static fn (object $row): bool => (string) $row->product_no === $selectedProductNo)
                ->values();
        }

        if ($q !== '') {
            $keyword = mb_strtoupper($q);
            $filteredRows = $filteredRows
                ->filter(static function (object $row) use ($keyword): bool {
                    $searchable = mb_strtoupper(implode(' ', [
                        (string) $row->forecast_month,
                        (string) $row->forecast_year,
                        (string) $row->product_no,
                        (string) $row->product_name,
                        number_format((float) $row->forecast_units, 2, '.', ''),
                    ]));

                    return str_contains($searchable, $keyword);
                })
                ->values();
        }

        if ($mode === 'monthly') {
            $displayRows = $filteredRows
                ->filter(static fn (object $row): bool => (string) $row->forecast_month === $selectedMonth)
                ->sortByDesc(static fn (object $row): float => (float) $row->forecast_units)
                ->values();
            $contextLabel = $this->formatForecastMonthLabel($selectedMonth);
        } else {
            $displayRows = $filteredRows
                ->filter(static fn (object $row): bool => (string) $row->forecast_year === $selectedYear)
                ->groupBy('product_no')
                ->map(static function ($rows, string $productNo) use ($selectedYear): object {
                    $first = $rows->first();

                    return (object) [
                        'forecast_month' => (string) $selectedYear,
                        'forecast_year' => (string) $selectedYear,
                        'product_no' => $productNo,
                        'product_name' => (string) ($first->product_name ?? $productNo),
                        'forecast_units' => (float) $rows->sum(static fn (object $row): float => (float) ($row->forecast_units ?? 0)),
                    ];
                })
                ->sortByDesc(static fn (object $row): float => (float) $row->forecast_units)
                ->values();
            $contextLabel = $selectedYear !== '' ? $selectedYear : 'Year';
        }

        $topForecast = $displayRows->first();
        $forecastMessage = $topForecast
            ? sprintf(
                '%s (%s) is expected to sell %s units in %s.',
                (string) ($topForecast->product_name ?? 'N/A'),
                (string) ($topForecast->product_no ?? 'N/A'),
                number_format((float) ($topForecast->forecast_units ?? 0)),
                $contextLabel
            )
            : 'No forecast data found for the selected filters.';

        $trendRows = $filteredRows;
        if ($mode === 'yearly' && $selectedYear !== '') {
            $trendRows = $trendRows
                ->filter(static fn (object $row): bool => (string) $row->forecast_year === $selectedYear)
                ->values();
        }

        $trendByMonth = $trendRows
            ->groupBy('forecast_month')
            ->map(static fn ($rows): float => (float) $rows->sum(static fn (object $row): float => (float) ($row->forecast_units ?? 0)))
            ->sortKeys()
            ->all();

        $chartTopRows = $displayRows->take(10)->values();
        $chartTopLabels = $chartTopRows
            ->map(static fn (object $row): string => (string) ($row->product_no ?: $row->product_name))
            ->all();
        $chartTopValues = $chartTopRows
            ->map(static fn (object $row): float => (float) ($row->forecast_units ?? 0))
            ->all();
        $chartTopNames = $chartTopRows
            ->map(static fn (object $row): string => (string) ($row->product_name ?? ''))
            ->all();

        $totalForecastUnits = (float) $displayRows->sum(static fn (object $row): float => (float) ($row->forecast_units ?? 0));
        $totalProducts = (int) $displayRows->count();
        $avgForecastUnits = $totalProducts > 0 ? ($totalForecastUnits / $totalProducts) : 0.0;
        $horizonPeriods = $filteredRows
            ->pluck('forecast_month')
            ->filter(static fn (string $month): bool => $month !== '')
            ->unique()
            ->count();

        return view('products.future', [
            'mode' => $mode,
            'q' => $q,
            'selectedProductNo' => $selectedProductNo,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'monthOptions' => $monthOptions,
            'yearOptions' => $yearOptions,
            'productOptions' => $productOptions,
            'displayRows' => $displayRows,
            'forecastMessage' => $forecastMessage,
            'contextLabel' => $contextLabel,
            'metrics' => [
                'products' => $totalProducts,
                'forecast_units' => $totalForecastUnits,
                'avg_units' => $avgForecastUnits,
                'horizon_periods' => $horizonPeriods,
            ],
            'topForecast' => $topForecast,
            'trendLabels' => array_keys($trendByMonth),
            'trendValues' => array_values($trendByMonth),
            'chartTopLabels' => $chartTopLabels,
            'chartTopValues' => $chartTopValues,
            'chartTopNames' => $chartTopNames,
            'futureError' => $futureError,
            'underStockCount' => (int) $conn->table('PRODUCTS')
                ->whereRaw("UPPER(NVL(STATUS, 'UNKNOWN')) = ?", ['UNDERSTOCK'])
                ->count(),
        ]);
    }

    public function photos(string $productNo): JsonResponse
    {
        $rows = DB::connection('oracle')
            ->table('PRODUCT_PHOTO')
            ->where('PRODUCT_ID', '=', $productNo)
            ->selectRaw("PHOTO_ID as photo_id,
                CASE WHEN MEDIA IS NOT NULL AND DBMS_LOB.GETLENGTH(MEDIA) <= 2000
                THEN UTL_RAW.CAST_TO_VARCHAR2(DBMS_LOB.SUBSTR(MEDIA, 2000, 1))
                ELSE NULL END as photo_path")
            ->orderBy('PHOTO_ID')
            ->get();

        $photos = $rows
            ->filter(fn (object $row): bool => trim((string) ($row->photo_path ?? '')) !== '')
            ->map(function (object $row): array {
                $path = (string) ($row->photo_path ?? '');
                $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    ? $path
                    : asset(ltrim($path, '/'));

                return ['photo_id' => (int) ($row->photo_id ?? 0), 'url' => $url];
            })
            ->values();

        return response()->json(['photos' => $photos]);
    }

    public function uploadPhoto(Request $request, string $productNo): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'max:4096']]);

        $exists = DB::connection('oracle')->table('PRODUCTS')->where('PRODUCT_NO', '=', $productNo)->exists();
        if (! $exists) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        $photoPath = $this->storePhoto($request->file('photo'));

        DB::connection('oracle')->statement(
            'INSERT INTO PRODUCT_PHOTO (PRODUCT_ID, MEDIA, CREATED_AT, UPDATED_AT)
             VALUES (:product_id, TO_BLOB(UTL_RAW.CAST_TO_RAW(:media)), SYSTIMESTAMP, SYSTIMESTAMP)',
            ['product_id' => $productNo, 'media' => $photoPath]
        );

        $photoId = (int) DB::connection('oracle')
            ->table('PRODUCT_PHOTO')
            ->where('PRODUCT_ID', '=', $productNo)
            ->orderByDesc('PHOTO_ID')
            ->value('PHOTO_ID');

        return response()->json([
            'photo_id' => $photoId,
            'url' => asset($photoPath),
        ]);
    }

    public function destroyPhoto(string $productNo, int $photoId): JsonResponse
    {
        $deleted = DB::connection('oracle')
            ->table('PRODUCT_PHOTO')
            ->where('PRODUCT_ID', '=', $productNo)
            ->where('PHOTO_ID', '=', $photoId)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Photo not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function defaultPhoto(string $productNo, int $photoId): JsonResponse
    {
        $conn = DB::connection('oracle');

        // Read all paths in current order
        $rows = $conn->table('PRODUCT_PHOTO')
            ->where('PRODUCT_ID', '=', $productNo)
            ->selectRaw("PHOTO_ID as photo_id,
                CASE WHEN MEDIA IS NOT NULL AND DBMS_LOB.GETLENGTH(MEDIA) <= 2000
                THEN UTL_RAW.CAST_TO_VARCHAR2(DBMS_LOB.SUBSTR(MEDIA, 2000, 1))
                ELSE NULL END as photo_path")
            ->orderBy('PHOTO_ID')
            ->get()
            ->filter(fn (object $row): bool => trim((string) ($row->photo_path ?? '')) !== '')
            ->values();

        $chosen = $rows->firstWhere('photo_id', $photoId);
        if (! $chosen) {
            return response()->json(['error' => 'Photo not found.'], 404);
        }

        // Reorder: chosen first, then the rest in original order
        $reordered = collect([(string) ($chosen->photo_path)])
            ->concat(
                $rows->filter(fn (object $r): bool => (int) ($r->photo_id ?? 0) !== $photoId)
                    ->map(fn (object $r): string => (string) ($r->photo_path ?? ''))
            );

        // Delete all then reinsert in new order
        $conn->table('PRODUCT_PHOTO')->where('PRODUCT_ID', '=', $productNo)->delete();

        foreach ($reordered as $path) {
            $conn->statement(
                'INSERT INTO PRODUCT_PHOTO (PRODUCT_ID, MEDIA, CREATED_AT, UPDATED_AT)
                 VALUES (:product_id, TO_BLOB(UTL_RAW.CAST_TO_RAW(:media)), SYSTIMESTAMP, SYSTIMESTAMP)',
                ['product_id' => $productNo, 'media' => $path]
            );
        }

        return response()->json(['success' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_no' => ['required', 'string', 'max:20'],
            'product_name' => ['required', 'string', 'max:80'],
            'product_type' => ['required', 'integer'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'profit_percent' => ['required', 'numeric', 'min:0'],
            'unit_measure' => ['required', 'string', 'max:20'],
            'qty_on_hand' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $typeId = (int) $validated['product_type'];
        $typeExists = DB::connection('oracle')
            ->table('PRODUCT_TYPE')
            ->where('PRODUCTTYPE_ID', '=', $typeId)
            ->exists();
        if (! $typeExists) {
            return back()->with('error', 'Selected product type was not found.');
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storePhoto($request->file('photo'));
        }

        try {
            DB::connection('oracle')->insert(
                'INSERT INTO PRODUCTS (PRODUCT_NO, PRODUCT_NAME, PRODUCT_TYPE, SELL_PRICE, COST_PRICE, PROFIT_PERCENT, UNIT_MEASURE, QTY_ON_HAND, STATUS)
                 VALUES (:product_no, :product_name, :product_type, :sell_price, :cost_price, :profit_percent, :unit_measure, :qty_on_hand, :status)',
                [
                    'product_no' => (string) $validated['product_no'],
                    'product_name' => (string) $validated['product_name'],
                    'product_type' => $typeId,
                    'sell_price' => (float) $validated['sell_price'],
                    'cost_price' => (float) $validated['cost_price'],
                    'profit_percent' => (float) $validated['profit_percent'],
                    'unit_measure' => (string) $validated['unit_measure'],
                    'qty_on_hand' => (int) $validated['qty_on_hand'],
                    'status' => $validated['status'] !== null && $validated['status'] !== ''
                        ? (string) $validated['status']
                        : null,
                ]
            );

            if ($photoPath !== null) {
                DB::connection('oracle')->statement(
                    'INSERT INTO PRODUCT_PHOTO (PRODUCT_ID, MEDIA, CREATED_AT, UPDATED_AT)
                     VALUES (:product_id, TO_BLOB(UTL_RAW.CAST_TO_RAW(:media)), SYSTIMESTAMP, SYSTIMESTAMP)',
                    ['product_id' => (string) $validated['product_no'], 'media' => $photoPath]
                );
            }
        } catch (QueryException $e) {
            $message = str_contains($e->getMessage(), 'ORA-00001')
                ? 'Product code already exists.'
                : 'Failed to create product: '.$e->getMessage();

            return back()->with('error', $message)->withInput();
        }

        return back()->with('success', 'Product created.');
    }

    private function formatForecastMonthLabel(string $month): string
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }

        try {
            $date = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month);

            return $date->format('M Y');
        } catch (\Throwable) {
            return $month;
        }
    }

    public function update(Request $request, string $productNo): RedirectResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:80'],
            'product_type' => ['required', 'integer'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'profit_percent' => ['required', 'numeric', 'min:0'],
            'unit_measure' => ['required', 'string', 'max:20'],
            'qty_on_hand' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $typeId = (int) $validated['product_type'];
        $typeExists = DB::connection('oracle')
            ->table('PRODUCT_TYPE')
            ->where('PRODUCTTYPE_ID', '=', $typeId)
            ->exists();
        if (! $typeExists) {
            return back()->with('error', 'Selected product type was not found.');
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storePhoto($request->file('photo'));
        }

        try {
            DB::connection('oracle')
                ->table('PRODUCTS')
                ->where('PRODUCT_NO', '=', $productNo)
                ->update([
                    'PRODUCT_NAME' => (string) $validated['product_name'],
                    'PRODUCT_TYPE' => $typeId,
                    'SELL_PRICE' => (float) $validated['sell_price'],
                    'COST_PRICE' => (float) $validated['cost_price'],
                    'PROFIT_PERCENT' => (float) $validated['profit_percent'],
                    'UNIT_MEASURE' => (string) $validated['unit_measure'],
                    'QTY_ON_HAND' => (int) $validated['qty_on_hand'],
                    'STATUS' => $validated['status'] !== null && $validated['status'] !== ''
                        ? (string) $validated['status']
                        : null,
                ]);

            if ($photoPath !== null) {
                DB::connection('oracle')->statement(
                    'INSERT INTO PRODUCT_PHOTO (PRODUCT_ID, MEDIA, CREATED_AT, UPDATED_AT)
                     VALUES (:product_id, TO_BLOB(UTL_RAW.CAST_TO_RAW(:media)), SYSTIMESTAMP, SYSTIMESTAMP)',
                    ['product_id' => $productNo, 'media' => $photoPath]
                );
            }
        } catch (QueryException $e) {
            return back()->with('error', 'Failed to update product: '.$e->getMessage());
        }

        return back()->with('success', "Product {$productNo} updated.");
    }

    private function storePhoto(UploadedFile $photo): string
    {
        $directory = public_path('images/products');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $ext = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
        $filename = uniqid('product_', true).'.'.$ext;
        $photo->move($directory, $filename);

        return 'images/products/'.$filename;
    }

    public function createType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type_name' => ['required', 'string', 'max:60'],
        ]);

        try {
            DB::connection('oracle')->insert(
                'INSERT INTO PRODUCT_TYPE (PRODUCTYPE_NAME) VALUES (:type_name)',
                ['type_name' => (string) $validated['type_name']]
            );
        } catch (QueryException $e) {
            return back()->with('error', 'Failed to create product type: '.$e->getMessage());
        }

        return back()->with('success', 'Product type created.');
    }

    public function updateType(Request $request, int $typeId): RedirectResponse
    {
        $validated = $request->validate([
            'type_name' => ['required', 'string', 'max:60'],
        ]);

        try {
            DB::connection('oracle')
                ->table('PRODUCT_TYPE')
                ->where('PRODUCTTYPE_ID', '=', $typeId)
                ->update([
                    'PRODUCTYPE_NAME' => (string) $validated['type_name'],
                ]);
        } catch (QueryException $e) {
            return back()->with('error', 'Failed to update product type: '.$e->getMessage());
        }

        return back()->with('success', 'Product type updated.');
    }

    public function updateAlertStock(Request $request, int $alertStockNo): RedirectResponse
    {
        $validated = $request->validate([
            'lower_qty' => ['required', 'integer', 'min:0'],
            'higher_qty' => ['required', 'integer', 'min:0'],
        ]);

        $lower = (int) $validated['lower_qty'];
        $higher = (int) $validated['higher_qty'];
        if ($higher < $lower) {
            return back()->with('error', 'Higher quantity must be greater than or equal to lower quantity.');
        }

        try {
            $updated = DB::connection('oracle')
                ->table('ALERT_STOCKS')
                ->where('ALERT_STOCK_NO', '=', $alertStockNo)
                ->update([
                    'LOWER_QTY' => $lower,
                    'HIGHER_QTY' => $higher,
                ]);
        } catch (QueryException $e) {
            return back()->with('error', 'Failed to update alert stock: '.$e->getMessage());
        }

        if ($updated === 0) {
            return back()->with('error', 'Alert stock record not found.');
        }

        return back()->with('success', 'Alert stock updated.');
    }
}
