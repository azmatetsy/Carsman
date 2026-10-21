<?php

declare(strict_types=1);

use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\PublicDirectoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDirectoryController::class, 'index'])->name('directory.home');
Route::get('/shops/{shop:slug}', [PublicDirectoryController::class, 'show'])->name('directory.shops.show');
Route::get('/account', [CustomerAccountController::class, 'index'])->name('customer.account');
