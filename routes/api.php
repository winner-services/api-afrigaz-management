<?php

use App\Http\Controllers\Api\Account\AccountController;
use App\Http\Controllers\Api\Auth\AuthenticationController;
use App\Http\Controllers\Api\Branches\BrancheController;
use App\Http\Controllers\Api\Bulk\BulkPurchaseController;
use App\Http\Controllers\Api\CashCategory\CashCategoryController;
use App\Http\Controllers\Api\Charoit\CharoitController;
use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\Currency\CurrencyController;
use App\Http\Controllers\Api\Customer\CustomerController;
use App\Http\Controllers\Api\Dristributor\DistributorController;
use App\Http\Controllers\Api\EntryStock\EntryStockController;
use App\Http\Controllers\Api\Filling\FillingController;
use App\Http\Controllers\Api\MovementStock\MovementController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\Products\CategoryController;
use App\Http\Controllers\Api\Products\ProductController;
use App\Http\Controllers\Api\Products\UnitController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Sale\ReturnSaleController;
use App\Http\Controllers\Api\Sale\SaleController;
use App\Http\Controllers\Api\Sipplier\SupplierController;
use App\Http\Controllers\Api\StockByBranche\StockController;
use App\Http\Controllers\Api\Tank\TankController;
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
            Route::get('/userGetOptionsData', 'getAllUsersOptions');
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

        Route::controller(TankController::class)->group(function () {
            Route::post('/tankStoreData', 'store');
            Route::post('/tankAddGas', 'addGas');
            Route::get('/tankMovementGetAllData', 'history');
            Route::post('/tankAdjust', 'adjust');
            Route::get('/tankGetAllData', 'index');
            Route::get('/tankGetOptionsData', 'getOptionTank');
            Route::get('/approvisionnementGetAllData', 'getAddGasHistory');
            Route::put('/tankUpdate/{id}', 'update');
            Route::put('/tankDelete/{id}', 'destroy');
        });

        Route::controller(CategoryController::class)->group(function () {
            Route::get('/categoryGetOptionsData', 'getCategoryOptions');
            Route::post('/categoryStoreData', 'storeCategory');
        });

        Route::controller(ProductController::class)->group(function () {
            Route::get('/productGetAllData', 'index');
            Route::get('/productGetOptionsData', 'getProductOptions');
            Route::get('/getEmptyProductOptions', 'getEmptyProductOptions');
            Route::post('/productStoreData', 'store');
            Route::put('/productUpdate/{id}', 'update');
            Route::put('/productDelete/{id}', 'destroy');
            Route::get('/lowStockProductsGetData', 'lowStockProducts');
            Route::get('/getTransfertProductOptionsData', 'getTransfertProductOptionsData');
        });

        Route::controller(FillingController::class)->group(function () {
            Route::get('/fillingGetAllData', 'index');
            Route::post('/fillingStoreData', 'store');
        });

        Route::controller(CharoitController::class)->group(function () {
            Route::get('/charoitsGetAllData', 'index');
            Route::get('/charoitsGetOptionData', 'charoitGetOptionData');
            Route::post('/charoitsStoreData', 'store');
            Route::put('/charoitsUpdate/{id}', 'update');
            Route::put('/charoitsDelete/{id}', 'destroy');
        });

        Route::controller(DistributorController::class)->group(function () {
            Route::get('/distributorGetAllData', 'index');
            Route::get('/distributorsGetOptionData', 'getDistributorOptions');
            Route::post('/distributorStoreData', 'store');
            Route::put('/distributorUpdate/{id}', 'update');
            Route::put('/distributorDelete/{id}', 'destroy');
            Route::put('/distributorDisable/{id}', 'disableDistributor');
            Route::put('/distributorActivate/{id}', 'activateDistributor');
        });

        Route::controller(SupplierController::class)->group(function () {
            Route::get('/supplierGetAllData', 'index');
            Route::get('/suppliersGetOptionsData', 'getSupplierOptions');
            Route::post('/supplierStoreData', 'store');
            Route::put('/supplierUpdate/{id}', 'update');
            Route::put('/supplierDelete/{id}', 'destroy');
        });

        Route::controller(CustomerController::class)->group(function () {
            Route::get('/customerGetAllData', 'index');
            Route::get('/customersGetOptionsData', 'getCustomerOptions');
            Route::post('/customerStoreData', 'store');
            Route::put('/customerUpdate/{id}', 'update');
            Route::put('/customerDelete/{id}', 'destroy');
        });

        Route::controller(CashCategoryController::class)->group(function () {
            Route::get('/cashCategoriesGetAllData', 'index');
            Route::get('/cashCategoriesGetOptionsData', 'getCashCategoryOptions');
            Route::post('/cashCategoriesStoreData', 'store');
            Route::put('/cashCategoryUpdate/{id}', 'update');
            Route::put('/cashCategoryDelete/{id}', 'destroy');
        });

        Route::controller(AccountController::class)->group(function () {
            Route::get('/accountGetAllData', 'index');
            Route::get('/accountGetOptionsData', 'getAccountOptions');
            Route::post('/accountStoreData', 'store');
            Route::put('/accountUpdate/{id}', 'update');
            Route::put('/accountDelete/{id}', 'destroy');
            Route::get('/accountByBranchGetOptionsData', 'getAccountOptionsByBranch');
        });

        Route::controller(BulkPurchaseController::class)->group(function () {
            Route::get('/bulkPurchaseGetAllData', 'index');
            Route::post('/bulkPurchaseStoreData', 'store');
            Route::put('/bulkPurchaseUpdate/{id}', 'update');
            Route::put('/bulkPurchaseDelete/{id}', 'destroy');
            Route::put('/lostQuantityStore/{id}', 'lostQuantityStore');
        });
        Route::controller(EntryStockController::class)->group(function () {
            Route::get('/stockEntrieGetAllData', 'index');
            Route::post('/stockEntriesStore', 'store');
        });

        Route::controller(TransefrController::class)->group(function () {
            Route::get('/transfersGetAllData', 'index');
            Route::post('/transferStockStoreData', 'transferBatch');
            Route::post('/adjustStockByBanch', 'adjust');
            Route::post('/removeQteStockByBanch', 'remove');
            Route::post('/returnProductStockByBanch', 'returnProduct');
            Route::post('/getTansfertProduct', 'getTansfertProduct');
        });

        Route::controller(StockController::class)->group(function () {
            Route::get('/stockByBranchGetAllData', 'index');
            Route::get('/stocksByBrancheGetData', 'getStockByBranche');
        });

        Route::controller(SaleController::class)->group(function () {
            Route::post('/saleStoreData', 'store');
            // Route::post('/saleCancel/{id}/', 'cancel');
            Route::get('/salesGetAllData', 'index');
            Route::get('/salesByBranchGetData', 'indexByBranche');
        });

        Route::controller(ReturnSaleController::class)->group(function () {
            Route::post('/salesReturn/{id}', 'returnWithRefund');
            Route::get('/returnsGetAllData', 'getCancellations');
        });

        Route::controller(MovementController::class)->group(function () {
            Route::get('/stockMovementGetAllData', 'index');
        });

        Route::controller(CurrencyController::class)->group(function () {
            Route::get('/currencyGetAllData', 'index');
            Route::post('/currencyStoreData', 'store');
            Route::put('/currencyUpdate/{id}', 'update');
            Route::put('/currencyDelete/{id}', 'destroy');
        });
        Route::controller(UnitController::class)->group(function () {
            Route::get('/unitGetOptionsData', 'getUnitsOptions');
            Route::post('/unitStoreData', 'storeUnit');
        });
    });
});
