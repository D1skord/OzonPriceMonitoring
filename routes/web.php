<?php

use App\Http\Controllers\UserProductStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/stats/products/{userProduct}', UserProductStatsController::class)->name('stats.user-product');

if (app()->isLocal()) {
    Route::get('/dev/vk-chat', [App\Http\Controllers\Dev\VkChatController::class, 'index']);
    Route::post('/dev/vk-chat/send', [App\Http\Controllers\Dev\VkChatController::class, 'send']);
}
