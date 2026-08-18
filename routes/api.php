<?php

use App\Http\Controllers\Api\OrderController;  
use App\Http\Controllers\Api\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Laravel\Passport\Http\Middleware\CheckToken;

// created by install:api --passport — returns the authenticated user
// we'll use this in step 5 to verify tokens work
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

//  public read endpoints (no auth yet, that comes in step 5) ↓↓↓
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

// public — anyone may register or log in
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// requires a valid token — you can't log out without being logged in
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
// now requires a valid token
Route::post('/orders', [OrderController::class, 'store'])->middleware('auth:api');

Route::get('/orders', [OrderController::class, 'index'])->middleware('auth:api');

// requires a valid token AND the events:create scope on it

  Route::post('/events', [EventController::class, 'store'])
    ->middleware(['auth:api', CheckToken::using('events:create')]);