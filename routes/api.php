<?php

use App\Http\Controllers\Api\OrderController;  
use App\Http\Controllers\Api\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// created by install:api --passport — returns the authenticated user
// we'll use this in step 5 to verify tokens work
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

//  public read endpoints (no auth yet, that comes in step 5) ↓↓↓
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);

Route::post('/orders', [OrderController::class, 'store']);