<?php

use App\Http\Controllers\UserProductStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/stats/products/{userProduct}', UserProductStatsController::class)->name('stats.user-product');
