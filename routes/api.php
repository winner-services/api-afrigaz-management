<?php

use App\Http\Controllers\Api\Auth\AuthenticationController;
use App\Http\Controllers\Api\Branches\BrancheController;
use App\Http\Controllers\Api\Bulk\Bulk_Purchase;
use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\Products\CategoryController;
use App\Http\Controllers\Api\Products\ProductController;
use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Sipplier\SupplierController;
use App\Http\Controllers\Api\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthenticationController::class, 'login']);
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('/logout', [AuthenticationController::class, 'logout']);
        });
    });

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

    Route::controller(Bulk_Purchase::class)->group(function () {
        Route::get('/bulkPurchaseGetAllData', 'index');
        Route::post('/bulkPurchaseStoreData', 'store');
        Route::put('/bulkPurchaseUpdate/{id}', 'update');
        Route::get('/bulkPurchaseDelete/{id}', 'destroy');
    });
});
