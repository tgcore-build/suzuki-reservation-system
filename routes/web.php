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
    Route::get('records/{record}/photo/{field}', [\App\Http\Controllers\RecordController::class, 'photo'])
        ->whereIn('field', \App\Models\Record::PHOTO_FIELDS)->name('records.photo');
});

// お客様向けオンライン予約（ログイン不要）
Route::get('/book', [\App\Http\Controllers\BookingController::class, 'index'])->name('booking.index');
Route::post('/book', [\App\Http\Controllers\BookingController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');
Route::get('/book/complete', [\App\Http\Controllers\BookingController::class, 'complete'])->name('booking.complete');

// お客様向け：簡易ログイン（電話番号＋生年月日）と利用履歴（さらにメールの確認コード）
Route::prefix('my')->name('my.')->controller(\App\Http\Controllers\MyPageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('login', 'loginForm')->name('login');
    Route::post('login', 'login')->middleware('throttle:10,1');
    Route::post('logout', 'logout')->name('logout');
    Route::get('verify', 'verifyForm')->name('verify');
    Route::post('verify', 'verify')->middleware('throttle:10,1');
    Route::post('verify/send', 'sendCode')->middleware('throttle:3,1')->name('verify.send');
    Route::get('birthday', 'birthdayForm')->name('birthday');
    Route::post('birthday/send', 'birthdaySendCode')->middleware('throttle:3,1')->name('birthday.send');
    Route::post('birthday', 'birthdayRegister')->middleware('throttle:10,1');
    Route::get('records/{record}/photo/{field}', 'recordPhoto')->whereIn('field', \App\Models\Record::PHOTO_FIELDS)->name('records.photo');
    Route::get('line', 'lineRedirect')->name('line');
    Route::get('line/callback', 'lineCallback')->name('line.callback');
    Route::post('line/unlink', 'lineUnlink')->name('line.unlink');
    Route::get('counseling/{reservation}', 'counselingForm')->name('counseling');
    Route::put('counseling/{reservation}', 'counseling');
    Route::get('counseling/{reservation}/done', 'counselingDone')->name('counseling.done');
});

// 顧客のCSV取り込み（※ /customers/import は顧客詳細と衝突するので別のURLにしています）
Route::middleware('auth')->prefix('customer-import')->name('customer-import.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CustomerImportController::class, 'create'])->name('create');
    Route::get('template', [\App\Http\Controllers\CustomerImportController::class, 'template'])->name('template');
    Route::post('preview', [\App\Http\Controllers\CustomerImportController::class, 'preview'])->name('preview');
    Route::post('confirm', [\App\Http\Controllers\CustomerImportController::class, 'confirm'])->name('confirm');
});

// 予約表（週表示・2週間表示）
Route::get('/schedule', [\App\Http\Controllers\ScheduleController::class, 'index'])->middleware('auth')->name('schedule.index');

// AI提案（お客様向け・ログイン不要）
Route::get('/ai', [\App\Http\Controllers\AiAdviceController::class, 'index'])->name('ai.index');
Route::post('/ai', [\App\Http\Controllers\AiAdviceController::class, 'store'])->middleware('throttle:5,1')->name('ai.store');
Route::post('/ai/reset', [\App\Http\Controllers\AiAdviceController::class, 'reset'])->name('ai.reset');

// AI相談の設定（管理者）
Route::middleware('auth')->group(function () {
    Route::get('ai-guidelines', [\App\Http\Controllers\AiGuidelineController::class, 'edit'])->name('ai-guidelines.edit');
    Route::put('ai-guidelines', [\App\Http\Controllers\AiGuidelineController::class, 'update'])->name('ai-guidelines.update');
});

// AI提案結果の一覧（管理者）
Route::resource('ai-sessions', \App\Http\Controllers\AiSessionController::class)->only(['index', 'show'])->middleware('auth');
