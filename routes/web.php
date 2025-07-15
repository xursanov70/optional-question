<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;



Route::post('/import_data', [QuestionController::class, 'importData'])->name('import_data');
Route::get('/questions', [QuestionController::class, 'questions'])->name('questions');
Route::get("/", [QuestionController::class, "showTestForm"]);