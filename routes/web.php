<?php

use App\Http\Controllers\AdminRbacController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\EcommerceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSearchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StaffAuthController;
use App\Support\StaffAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/login', [StaffAuthController::class, 'showLogin'])->name('staff.login');
Route::post('/login', [StaffAuthController::class, 'login'])->name('staff.login.attempt');
Route::get('/logout/loading', [StaffAuthController::class, 'showLogoutLoading'])->name('staff.logout.loading');
Route::get('/signup', [StaffAuthController::class, 'showSignup'])->name('staff.signup');
Route::post('/signup', [StaffAuthController::class, 'signup'])->name('staff.signup.create');

Route::middleware('staff.auth')->group(function (): void {
    Route::get('/login/loading', [StaffAuthController::class, 'showLoginLoading'])->name('staff.login.loading');
    Route::post('/logout', [StaffAuthController::class, 'logout'])->name('staff.logout');

    Route::get('/', function () {
        if (StaffAuth::can('dashboard.manage')) {
            return redirect()->route('admin.dashboard');
        }

        if (StaffAuth::can('dashboard.read')) {
            return redirect()->route('store.home');
        }

        $fallbackRoutes = [
            'orders.read' => 'store.orders',
            'shop.read' => 'store.catalog',
            'checkout.process' => 'store.cart',
            'invoices.read' => 'invoices.index',
            'purchases.read' => 'purchases.index',
            'clients.read' => 'clients.index',
            'client-depts.read' => 'client-depts.index',
            'currencies.read' => 'currencies.index',
            'products.read' => 'products.index',
            'stock-status.read' => 'products.status',
            'future-stock.read' => 'products.status.future',
            'employees.read' => 'employees.index',
            'jobs.read' => 'jobs.index',
            'users.read' => 'admin.rbac.users.index',
            'roles.read' => 'admin.rbac.roles.index',
            'permissions.read' => 'admin.rbac.permissions.index',
            'system.audit' => 'store.deep-check',
        ];

        foreach ($fallbackRoutes as $ability => $routeName) {
            if (StaffAuth::can($ability)) {
                return redirect()->route($routeName);
            }
        }

        abort(403, 'Your account has no accessible module.');
    })->name('dashboard.entry');

    Route::get('/dashboard', [EcommerceController::class, 'home'])
        ->middleware('staff.ability:dashboard.read')
        ->name('store.home');

    Route::get('/admin/dashboard', [AdminRbacController::class, 'dashboard'])
        ->middleware('staff.ability:dashboard.manage')
        ->name('admin.dashboard');

    Route::get('/admin/rbac/users', [AdminRbacController::class, 'usersIndex'])
        ->middleware('staff.ability:users.read')
        ->name('admin.rbac.users.index');
    Route::get('/admin/rbac/users/create', [AdminRbacController::class, 'usersCreate'])
        ->middleware('staff.ability:users.manage')
        ->name('admin.rbac.users.create');
    Route::post('/admin/rbac/users', [AdminRbacController::class, 'usersStore'])
        ->middleware('staff.ability:users.manage')
        ->name('admin.rbac.users.store');
    Route::get('/admin/rbac/users/{userId}/edit', [AdminRbacController::class, 'usersEdit'])
        ->middleware('staff.ability:users.manage')
        ->name('admin.rbac.users.edit');
    Route::patch('/admin/rbac/users/{userId}', [AdminRbacController::class, 'usersUpdate'])
        ->middleware('staff.ability:users.manage')
        ->name('admin.rbac.users.update');
    Route::delete('/admin/rbac/users/{userId}', [AdminRbacController::class, 'usersDestroy'])
        ->middleware('staff.ability:users.manage')
        ->name('admin.rbac.users.destroy');

    Route::get('/admin/rbac/roles', [AdminRbacController::class, 'rolesIndex'])
        ->middleware('staff.ability:roles.read')
        ->name('admin.rbac.roles.index');
    Route::get('/admin/rbac/roles/create', [AdminRbacController::class, 'rolesCreate'])
        ->middleware('staff.ability:roles.manage')
        ->name('admin.rbac.roles.create');
    Route::post('/admin/rbac/roles', [AdminRbacController::class, 'rolesStore'])
        ->middleware('staff.ability:roles.manage')
        ->name('admin.rbac.roles.store');
    Route::get('/admin/rbac/roles/{groupId}/edit', [AdminRbacController::class, 'rolesEdit'])
        ->middleware('staff.ability:roles.manage')
        ->name('admin.rbac.roles.edit');
    Route::patch('/admin/rbac/roles/{groupId}', [AdminRbacController::class, 'rolesUpdate'])
        ->middleware('staff.ability:roles.manage')
        ->name('admin.rbac.roles.update');
    Route::delete('/admin/rbac/roles/{groupId}', [AdminRbacController::class, 'rolesDestroy'])
        ->middleware('staff.ability:roles.manage')
        ->name('admin.rbac.roles.destroy');

    Route::get('/admin/rbac/permissions', [AdminRbacController::class, 'permissionsIndex'])
        ->middleware('staff.ability:permissions.read')
        ->name('admin.rbac.permissions.index');
    Route::get('/admin/rbac/permissions/create', [AdminRbacController::class, 'permissionsCreate'])
        ->middleware('staff.ability:permissions.manage')
        ->name('admin.rbac.permissions.create');
    Route::post('/admin/rbac/permissions', [AdminRbacController::class, 'permissionsStore'])
        ->middleware('staff.ability:permissions.manage')
        ->name('admin.rbac.permissions.store');
    Route::get('/admin/rbac/permissions/{formId}/edit', [AdminRbacController::class, 'permissionsEdit'])
        ->middleware('staff.ability:permissions.manage')
        ->name('admin.rbac.permissions.edit');
    Route::patch('/admin/rbac/permissions/{formId}', [AdminRbacController::class, 'permissionsUpdate'])
        ->middleware('staff.ability:permissions.manage')
        ->name('admin.rbac.permissions.update');
    Route::delete('/admin/rbac/permissions/{formId}', [AdminRbacController::class, 'permissionsDestroy'])
        ->middleware('staff.ability:permissions.manage')
        ->name('admin.rbac.permissions.destroy');

    Route::get('/shop', [EcommerceController::class, 'catalog'])
        ->middleware('staff.ability:shop.read')
        ->name('store.catalog');

    Route::get('/cart', [EcommerceController::class, 'cart'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.cart');
    Route::post('/cart/items', [EcommerceController::class, 'addToCart'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.cart.add');
    Route::patch('/cart/items/{productNo}', [EcommerceController::class, 'updateCart'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.cart.update');
    Route::delete('/cart/items/{productNo}', [EcommerceController::class, 'removeFromCart'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.cart.remove');
    Route::post('/cart/clear', [EcommerceController::class, 'clearCart'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.cart.clear');

    Route::get('/checkout', [EcommerceController::class, 'checkout'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.checkout');
    Route::post('/checkout', [EcommerceController::class, 'placeOrder'])
        ->middleware('staff.ability:checkout.process')
        ->name('store.checkout.place');

    Route::get('/orders', [EcommerceController::class, 'orders'])
        ->middleware('staff.ability:orders.read')
        ->name('store.orders');
    Route::get('/orders/{invoiceNo}', [EcommerceController::class, 'showOrder'])
        ->middleware('staff.ability:orders.read')
        ->name('store.orders.show');
    Route::patch('/orders/{invoiceNo}/complete', [EcommerceController::class, 'completeOrder'])
        ->middleware('staff.ability:orders.manage')
        ->name('store.orders.complete');
    Route::post('/orders/{invoiceNo}/repay', [EcommerceController::class, 'repayOrder'])
        ->middleware('staff.ability:client-depts.manage')
        ->name('store.orders.repay');
    Route::get('/total-sales', [EcommerceController::class, 'totalSales'])
        ->middleware('staff.ability:total-sales.read')
        ->name('total-sales.index');
    Route::get('/invoices', [EcommerceController::class, 'invoices'])
        ->middleware('staff.ability:invoices.read')
        ->name('invoices.index');
    Route::post('/invoices', [EcommerceController::class, 'storeInvoice'])
        ->middleware('staff.ability:invoices.manage')
        ->name('invoices.store');
    Route::post('/invoices/{invoiceNo}/items', [EcommerceController::class, 'addInvoiceItems'])
        ->middleware('staff.ability:invoices.manage')
        ->name('invoices.items.store');
    Route::get('/bakong/check-transaction', [EcommerceController::class, 'checkBakongTransaction'])
        ->name('bakong.check_transaction');
    Route::delete('/invoices/{invoiceNo}/items/{productNo}', [EcommerceController::class, 'removeInvoiceItem'])
        ->middleware('staff.ability:invoices.manage')
        ->name('invoices.items.destroy');
    Route::get('/products/price', [EcommerceController::class, 'productPrice'])
        ->middleware('staff.ability:orders.read')
        ->name('products.price');
    Route::get('/products/cost', [EcommerceController::class, 'productCost'])
        ->middleware('staff.ability:purchases.manage')
        ->name('products.cost');
    Route::get('/purchases', [EcommerceController::class, 'purchases'])
        ->middleware('staff.ability:purchases.read')
        ->name('purchases.index');
    Route::get('/purchase-history', [EcommerceController::class, 'purchaseHistory'])
        ->middleware('staff.ability:purchases.read')
        ->name('purchases.history');
    Route::post('/purchases', [EcommerceController::class, 'storePurchase'])
        ->middleware('staff.ability:purchases.manage')
        ->name('purchases.store');
    Route::post('/purchases/{purchaseNo}/items', [EcommerceController::class, 'addPurchaseItems'])
        ->middleware('staff.ability:purchases.manage')
        ->name('purchases.items.store');

    Route::get('/clients', [ClientController::class, 'index'])
        ->middleware('staff.ability:clients.read')
        ->name('clients.index');
    Route::get('/clients/data', [ClientController::class, 'data'])
        ->middleware('staff.ability:clients.read')
        ->name('clients.data');
    Route::post('/clients', [ClientController::class, 'store'])
        ->middleware('staff.ability:clients.manage')
        ->name('clients.store');
    Route::patch('/clients/{clientNo}', [ClientController::class, 'updateClient'])
        ->middleware('staff.ability:clients.manage')
        ->name('clients.update');
    Route::post('/client-types', [ClientController::class, 'createClientType'])
        ->middleware('staff.ability:client-types.manage')
        ->name('client-types.create');
    Route::patch('/client-types/{clientTypeId}', [ClientController::class, 'updateClientType'])
        ->middleware('staff.ability:client-types.manage')
        ->name('client-types.update');
    Route::get('/client-depts', [EcommerceController::class, 'clientDepts'])
        ->middleware('staff.ability:client-depts.read')
        ->name('client-depts.index');
    Route::get('/client-depts/history', [EcommerceController::class, 'deptHistory'])
        ->middleware('staff.ability:client-depts.read')
        ->name('client-depts.history');

    Route::get('/currencies', [CurrencyController::class, 'index'])
        ->middleware('staff.ability:currencies.read')
        ->name('currencies.index');
    Route::post('/currencies', [CurrencyController::class, 'store'])
        ->middleware('staff.ability:currencies.manage')
        ->name('currencies.store');
    Route::patch('/currencies', [CurrencyController::class, 'update'])
        ->middleware('staff.ability:currencies.manage')
        ->name('currencies.update');
    Route::delete('/currencies', [CurrencyController::class, 'destroy'])
        ->middleware('staff.ability:currencies.manage')
        ->name('currencies.destroy');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('staff.ability:products.read')
        ->name('products.index');
    Route::get('/products/status', [ProductController::class, 'status'])
        ->middleware('staff.ability:stock-status.read')
        ->name('products.status');
    Route::get('/products/status/future', [ProductController::class, 'future'])
        ->middleware('staff.ability:future-stock.read')
        ->name('products.status.future');
    Route::get('/products/{productNo}/photos', [ProductController::class, 'photos'])
        ->middleware('staff.ability:products.read')
        ->name('products.photos');
    Route::post('/products/{productNo}/photos', [ProductController::class, 'uploadPhoto'])
        ->middleware('staff.ability:products.manage')
        ->name('products.photos.upload');
    Route::delete('/products/{productNo}/photos/{photoId}', [ProductController::class, 'destroyPhoto'])
        ->middleware('staff.ability:products.manage')
        ->name('products.photos.destroy');
    Route::post('/products/{productNo}/photos/{photoId}/default', [ProductController::class, 'defaultPhoto'])
        ->middleware('staff.ability:products.manage')
        ->name('products.photos.default');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('staff.ability:products.manage')
        ->name('products.store');
    Route::patch('/products/{productNo}', [ProductController::class, 'update'])
        ->middleware('staff.ability:products.manage')
        ->name('products.update');
    Route::post('/alert-stocks', [ProductController::class, 'storeAlertStock'])
        ->middleware('staff.ability:stock-status.manage')
        ->name('alert-stocks.store');
    Route::patch('/alert-stocks/{alertStockNo}', [ProductController::class, 'updateAlertStock'])
        ->middleware('staff.ability:stock-status.manage')
        ->name('alert-stocks.update');
    Route::post('/product-types', [ProductController::class, 'createType'])
        ->middleware('staff.ability:product-types.manage')
        ->name('product-types.create');
    Route::patch('/product-types/{typeId}', [ProductController::class, 'updateType'])
        ->middleware('staff.ability:product-types.manage')
        ->name('product-types.update');
    Route::delete('/product-types/{typeId}', [ProductController::class, 'deleteType'])
        ->middleware('staff.ability:product-types.manage')
        ->name('product-types.delete');
    Route::get('/product-types/{typeId}/check-usage', [ProductController::class, 'checkTypeUsage'])
        ->middleware('staff.ability:product-types.manage')
        ->name('product-types.check-usage');

    Route::get('/oracle/deep-check', [EcommerceController::class, 'deepCheck'])
        ->middleware('staff.ability:system.audit')
        ->name('store.deep-check');

    Route::get('/oracle-test', function () {
        try {
            DB::connection('oracle')->getPdo();

            return 'connect success';
        } catch (\Exception $e) {
            return 'Could not connect to the database. Error: '.$e->getMessage();
        }
    })->middleware('staff.ability:system.audit');

    Route::get('/employees/search', [EmployeeSearchController::class, 'index'])
        ->middleware('staff.ability:employees.read')
        ->name('employees.search');
    Route::get('/employees/search/data', [EmployeeSearchController::class, 'data'])
        ->middleware('staff.ability:employees.read')
        ->name('employees.search.data');

    Route::get('/jobs', [EmployeeController::class, 'jobs'])
        ->middleware('staff.ability:jobs.read')
        ->name('jobs.index');
    Route::post('/jobs', [EmployeeController::class, 'jobsStore'])
        ->middleware('staff.ability:jobs.manage')
        ->name('jobs.store');

    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('staff.ability:employees.read')
        ->name('employees.index');
    Route::get('/employees/{employeeId}', [EmployeeController::class, 'show'])
        ->middleware('staff.ability:employees.read')
        ->name('employees.show');
    Route::post('/employees', [EmployeeController::class, 'store'])
        ->middleware('staff.ability:employees.manage')
        ->name('employees.store');
    Route::patch('/employees/{employeeId}', [EmployeeController::class, 'update'])
        ->middleware('staff.ability:employees.manage')
        ->name('employees.update');
    Route::delete('/employees/{employeeId}', [EmployeeController::class, 'destroy'])
        ->middleware('staff.ability:employees.manage')
        ->name('employees.destroy');
});
