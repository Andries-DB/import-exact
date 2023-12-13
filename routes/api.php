<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConnectController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/', [ConnectController::class, 'get']);
Route::get('/get/crm-account', [ConnectController::class, 'get']);
Route::get('/get/booking', [ConnectController::class, 'get']);


Route::get('/post/crm-account', [ConnectController::class, 'post']);
Route::get('/post/booking', [ConnectController::class, 'post']);
