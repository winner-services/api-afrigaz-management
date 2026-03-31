<?php

use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::controller(UserController::class)->group(function () {
        Route::get('/usersGetAllData', 'index');
        Route::get('usersGetOptionsData', 'getAllUsersOptions');
        Route::post('/userStoreData', 'store');
        Route::put('/userUpdateData/{id}', 'update');
        Route::delete('/userDestroyData/{id}', 'destroy');
        Route::put('/activateUser/{id}','userActivate');
        Route::put('/userDisable/{id}','disableUser');
    });

    Route::controller(CompanyController::class)->group(function () {
        Route::get('/aboutGetAllData', 'getData');
        Route::post('/aboutStoreData', 'store');
    });
});
