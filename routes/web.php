<?php

use App\Http\Controllers\Api\DocumentationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/docs', [DocumentationController::class, 'ui'])->name('api.docs');
Route::get('/api/docs/openapi.json', [DocumentationController::class, 'spec'])->name('api.docs.spec');
