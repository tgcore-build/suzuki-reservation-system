<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::resource('customers', \App\Http\Controllers\CustomerController::class)->middleware('auth');
Route::resource('menus', \App\Http\Controllers\MenuController::class)->except('show')->middleware('auth');
Route::middleware('auth')->group(function () {
    Route::get('business-hours', [\App\Http\Controllers\BusinessHourController::class, 'edit'])->name('business-hours.edit');
    Route::put('business-hours', [\App\Http\Controllers\BusinessHourController::class, 'update'])->name('business-hours.update');
});
