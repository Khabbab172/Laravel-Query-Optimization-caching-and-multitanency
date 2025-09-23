<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\UserSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login'])->name('login');

// Route::middleware(middleware: 'auth')->group(function () {

    Route::post('/user/search', [UserSearchController::class,'index'])->name('user.search');
    Route::post('/invoice/search', [InvoiceController::class,'index'])->name('invoice.search');
    Route::post('/dashboard/matric', [InvoiceController::class,'index'])->name('dashboard.matric');
    
// });