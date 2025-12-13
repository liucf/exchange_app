<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard', [
        'user' => auth()->user()->load(['assets', 'orders'])->toResource(),
    ]);
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->name('api.')->prefix('api')
    ->group(function () {
        Route::get('/profile', ProfileController::class)->name('profile');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{id}/cancel', [OrderController::class, 'destroy'])->name('orders.cancel');
    });

require __DIR__.'/settings.php';
