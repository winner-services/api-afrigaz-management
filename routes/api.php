<?php

use App\Http\Controllers\Api\AboutController;
use Illuminate\Support\Facades\Route;

Route::controller(AboutController::class)->group(function () {
    Route::get('/aboutGetAllData', 'getData');
    Route::post('/aboutStoreData', 'store');
});
