<?php

use App\Http\Controllers\ArtisanProfile\EditArtisanProfileController;
use App\Http\Controllers\ArtisanProfile\SaveArtisanProfileController;
use App\Http\Controllers\ContactRequest\ListContactRequestsController;
use App\Http\Controllers\ContactRequest\SubmitContactRequestController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'contact/index')->name('home');
Route::post('/contact-requests', SubmitContactRequestController::class)
    ->middleware('throttle:contact')
    ->name('contact-requests.store');
Route::inertia('/thank-you', 'contact/thank-you')->name('thank-you');

Route::get('/profile', EditArtisanProfileController::class)->name('profile.edit');
Route::post('/profile', SaveArtisanProfileController::class)->name('profile.store');

Route::get('/dashboard', ListContactRequestsController::class)->name('dashboard');
