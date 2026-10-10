<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

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
Route::resource('reservations', \App\Http\Controllers\ReservationController::class)->except('show')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('customers/{customer}/records/create', [\App\Http\Controllers\RecordController::class, 'create'])->name('records.create');
    Route::post('customers/{customer}/records', [\App\Http\Controllers\RecordController::class, 'store'])->name('records.store');
    Route::get('records/{record}/edit', [\App\Http\Controllers\RecordController::class, 'edit'])->name('records.edit');
    Route::put('records/{record}', [\App\Http\Controllers\RecordController::class, 'update'])->name('records.update');
    Route::delete('records/{record}', [\App\Http\Controllers\RecordController::class, 'destroy'])->name('records.destroy');
});

// お客様向けオンライン予約（ログイン不要）
Route::get('/book', [\App\Http\Controllers\BookingController::class, 'index'])->name('booking.index');
Route::post('/book', [\App\Http\Controllers\BookingController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');
Route::get('/book/complete', [\App\Http\Controllers\BookingController::class, 'complete'])->name('booking.complete');

// 顧客のCSV取り込み（※ /customers/import は顧客詳細と衝突するので別のURLにしています）
Route::middleware('auth')->prefix('customer-import')->name('customer-import.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CustomerImportController::class, 'create'])->name('create');
    Route::get('template', [\App\Http\Controllers\CustomerImportController::class, 'template'])->name('template');
    Route::post('preview', [\App\Http\Controllers\CustomerImportController::class, 'preview'])->name('preview');
    Route::post('confirm', [\App\Http\Controllers\CustomerImportController::class, 'confirm'])->name('confirm');
});
