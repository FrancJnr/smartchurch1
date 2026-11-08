<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\GivingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});
Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/home', function (Request $request) {
        return $request->user()->can('dashboard.view') ? redirect()->route('dashboard') : view('home');
    })->name('home');
    Route::get('/', [ReportController::class, 'dashboard'])->middleware('can:dashboard.view')->name('dashboard');
    Route::get('/members/lookup/{ekanisa}', [MemberController::class, 'lookup'])->middleware('can:givings.create')->name('members.lookup');
    Route::resource('members', MemberController::class)->only(['index'])->middleware('can:members.view');
    Route::resource('members', MemberController::class)->only(['create', 'store', 'edit', 'update'])->middleware('can:members.manage');
    Route::resource('givings', GivingController::class)->only(['index'])->middleware('can:givings.view');
    Route::resource('givings', GivingController::class)->only(['create', 'store'])->middleware('can:givings.create');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('can:reports.export')->name('reports.export');
    Route::get('/communications', [CommunicationController::class, 'index'])->middleware('can:communications.view')->name('communications.index');
    Route::get('/communications/create', [CommunicationController::class, 'create'])->middleware('can:communications.send')->name('communications.create');
    Route::post('/communications', [CommunicationController::class, 'store'])->middleware('can:communications.send')->name('communications.store');
    Route::get('/communications/{campaign}', [CommunicationController::class, 'show'])->middleware('can:communications.view')->name('communications.show');
    Route::post('/communications/{campaign}/send', [CommunicationController::class, 'send'])->middleware('can:communications.send')->name('communications.send');
    Route::middleware('can:access.manage')->group(function (): void {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    });
    Route::middleware('can:settings.manage')->prefix('settings')->group(function (): void {
        Route::get('/{catalog}', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::post('/{catalog}', [CatalogController::class, 'store'])->name('catalogs.store');
        Route::put('/{catalog}/{item}', [CatalogController::class, 'update'])->whereNumber('item')->name('catalogs.update');
    });
});
