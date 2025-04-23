<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('import/word-file', [QuestionController::class, 'importWordFile']);
Route::post('export/excel-data', [QuestionController::class, 'exportExcelData']);
Route::post('test', [QuestionController::class, 'test']);
