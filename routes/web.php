<?php

use App\Infrastructure\Http\Controller\User\CreateUserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::inertia('/users/create', 'users/create')->name('users.create');
Route::post('/users', CreateUserController::class)->name('users.store');
