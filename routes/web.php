<?php

use App\Http\Controllers\AnniversaryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MonthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WeekController;
use Illuminate\Support\Facades\Route;

/*
 | ログイン・新規登録・ログアウトのルートは Fortify が自動で用意します。
 | （/login, /register, /logout）ここには書きません。
 */

Route::get('/', fn () => auth()->check()
    ? redirect()->route('home')
    : view('welcome'))->name('top');

Route::middleware('auth')->group(function () {

    // --- まだ家族に入っていない人だけが通る道 ---
    Route::get('/family/setup', [FamilyController::class, 'setup'])->name('family.setup');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store');
    Route::post('/family/join', [FamilyController::class, 'join'])->name('family.join');

    // --- ここから先は家族に入っている人だけ ---
    Route::middleware('has.family')->group(function () {

        // ログイン後の飛び先。RouteServiceProvider::HOME = '/home' と一致させている
        Route::get('/home', [WeekController::class, 'index'])->name('home');

        // 月カレンダー表示
        Route::get('/month', [MonthController::class, 'index'])->name('month');

        Route::get('/family/invite', [FamilyController::class, 'invite'])->name('family.invite');

        // 地域の設定（天気予報のため）
        Route::get('/family/settings', [FamilyController::class, 'settings'])->name('family.settings');
        Route::post('/family/location', [FamilyController::class, 'saveLocation'])->name('family.location');
        Route::delete('/family/location', [FamilyController::class, 'clearLocation'])->name('family.location.clear');

        Route::resource('members', MemberController::class)->except(['show', 'create']);
        Route::resource('events', EventController::class)->except(['show', 'index']);
        Route::resource('lessons', LessonController::class)->except(['show']);
        Route::resource('tasks', TaskController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('anniversaries', AnniversaryController::class)->except(['show', 'create']);
    });
});
