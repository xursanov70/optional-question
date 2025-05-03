<?php

use App\Http\Controllers\QuestionController;
use App\Http\Controllers\Telegram\TelegramHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('import/word-file', [QuestionController::class, 'importWordFile']);
Route::post('store/image', [QuestionController::class, 'storeImage']);
Route::get("test", [TelegramHandler::class, "test"]);