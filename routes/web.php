<?php

use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('home');
});

Route::post('/import_data', [QuestionController::class, 'importData'])->name('import_data');
Route::get('/questions', [QuestionController::class, 'questions'])->name('questions');
