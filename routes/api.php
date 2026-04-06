<?php

use App\Http\Controllers\Api\Auth\AuthenticationController;
use App\Http\Controllers\Api\Branches\BrancheController;
use App\Http\Controllers\Api\Bulk\BulkPurchaseController;
use App\Http\Controllers\Api\CashCategory\CashCategoryController;
use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\Customer\CustomerController;
use App\Http\Controllers\Api\EntryStock\EntryStockController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\Products\CategoryController;
use App\Http\Controllers\Api\Products\ProductController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Sale\ReturnSaleController;
use App\Http\Controllers\Api\Sale\SaleController;
use App\Http\Controllers\Api\Sipplier\SupplierController;
use App\Http\Controllers\Api\Transfer\TransefrController;
use App\Http\Controllers\Api\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthenticationController::class, 'login']);
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('/logout', [AuthenticationController::class, 'logout']);
        });
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::controller(UserController::class)->group(function () {
            Route::get('/usersGetAllData', 'index');
            Route::get('/usersGetOptionsData', 'getAllUsersOptions');
            Route::post('/userStoreData', 'store');
            Route::put('/userUpdateData/{id}', 'update');
            Route::delete('/userDestroyData/{id}', 'destroy');
            Route::put('/userActivate/{id}', 'activateUser');
            Route::put('/userDisable/{id}', 'disableUser');
        });

        Route::controller(RoleController::class)->group(function () {
            Route::post('/roleStore', 'storeRole');
            Route::put('/roleUpdate/{id}', 'updateRole');
            Route::get('/rolesGetAllData', 'getRole');
        });
        Route::controller(PermissionController::class)->group(function () {
            Route::get('/permissionsGetAllData', 'getPemissionData');
            Route::get('/getPermissionDataByRole/{id}', 'getPermissionDataByRole');
        });
        Route::controller(CompanyController::class)->group(function () {
            Route::get('/aboutGetAllData', 'getData');
            Route::post('/aboutStoreData', 'store');
        });

        Route::controller(BrancheController::class)->group(function () {
            Route::get('/brancheGetAllData', 'get');
            Route::post('/brancheStoreData', 'storeData');
            Route::put('/brancheUpdate/{id}', 'update');
            Route::put('/brancheDelete/{id}', 'destroy');
        });

        Route::controller(CategoryController::class)->group(function () {
            Route::get('/categoryGetOptionsData', 'getCategoryOptions');
            Route::post('/categoryStoreData', 'storeCategory');
        });

        Route::controller(ProductController::class)->group(function () {
            Route::get('/productGetAllData', 'index');
            Route::get('/productGetOptionsData', 'getProductOptions');
            Route::post('/productStoreData', 'store');
            Route::put('/productUpdate/{id}', 'update');
            Route::put('/productDelete/{id}', 'destroy');
        });

        Route::controller(SupplierController::class)->group(function () {
            Route::get('/supplierGetAllData', 'index');
            Route::get('/suppliersGetOptionsData', 'getSupplierOptions');
            Route::post('/supplierStoreData', 'store');
            Route::put('/supplierUpdate/{id}', 'update');
            Route::get('/supplierDelete/{id}', 'destroy');
        });

        Route::controller(CustomerController::class)->group(function () {
            Route::get('/customerGetAllData', 'index');
            Route::get('/customersGetOptionsData', 'getCustomerOptions');
            Route::post('/customerStoreData', 'store');
            Route::put('/customerUpdate/{id}', 'update');
            Route::get('/customerDelete/{id}', 'destroy');
        });

        Route::controller(CashCategoryController::class)->group(function () {
            Route::get('/cashCategoriesGetAllData', 'index');
            Route::get('/cashCategoriesGetOptionsData', 'getCashCategoryOptions');
            Route::post('/cashCategoriesStoreData', 'store');
            Route::put('/cashCategoriesUpdate/{id}', 'update');
            Route::get('/cashCategoriesDelete/{id}', 'destroy');
        });

        Route::controller(BulkPurchaseController::class)->group(function () {
            Route::get('/bulkPurchaseGetAllData', 'index');
            Route::post('/bulkPurchaseStoreData', 'store');
            Route::put('/bulkPurchaseUpdate/{id}', 'update');
            Route::get('/bulkPurchaseDelete/{id}', 'destroy');
        });
        Route::controller(EntryStockController::class)->group(function () {
            Route::get('/stockEntrieGetAllData', 'index');
            Route::post('/stockEntriesStore', 'store');
        });

        Route::controller(TransefrController::class)->group(function () {
            Route::post('/transferStockStoreData', 'transferBatch');
            Route::post('/adjustStockByBanch', 'adjust');
            Route::post('/removeQteStockByBanch', 'remove');
            Route::post('/returnProductStockByBanch', 'returnProduct');
        });

        Route::controller(SaleController::class)->group(function () {
            Route::post('/saleStoreData', 'store');
            Route::post('/saleCancel/{id}/', 'cancel');
            // Route::get('/saleGetAllData', 'index');
        });

        Route::controller(ReturnSaleController::class)->group(function () {
            Route::post('/salesReturn/{id}', 'returnWithRefund');
            Route::get('/returnsGetAllData', 'getCancellations');
        });
    });
});
