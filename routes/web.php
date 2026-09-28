<?php

use App\Http\Controllers\FamilyController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MemberController;
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
    Route::post('/family',      [FamilyController::class, 'store'])->name('family.store');
    Route::post('/family/join', [FamilyController::class, 'join'])->name('family.join');

    // --- ここから先は家族に入っている人だけ ---
    Route::middleware('has.family')->group(function () {

        // WeekController に差し替える
        Route::get('/home', fn () => view('home-placeholder'))->name('home');

        Route::get('/family/invite', [FamilyController::class, 'invite'])->name('family.invite');

        Route::resource('members', MemberController::class)->except(['show', 'create']);
        Route::resource('events', EventController::class)->except(['show', 'index']);
        Route::resource('lessons', LessonController::class)->except(['show']);
    });
});
