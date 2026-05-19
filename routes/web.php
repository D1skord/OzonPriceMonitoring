<?php

use App\Http\Controllers\Dev\VkChatController;
use App\Http\Controllers\UserProductStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/stats/products/{userProduct}', UserProductStatsController::class)->name('stats.user-product');

if (app()->isLocal()) {
    Route::get('/dev/vk-chat', [VkChatController::class, 'index']);
    Route::post('/dev/vk-chat/send', [VkChatController::class, 'send']);
}
