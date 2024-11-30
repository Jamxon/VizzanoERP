<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::middleware('role:supervisor')->group(function () {
    Route::get('groups', [GroupController::class, 'index']);
    Route::post('groups',[GroupController::class, 'store']);
    Route::patch('groups/{group}', [GroupController::class,'update']);
    Route::delete('groups/{group}',[GroupController::class, 'delete']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'delete']);
    Route::patch('changeorderstatus', [OrderController::class, 'changeStatus']);
    Route::get('models', [ModelController::class, 'index']);
    Route::post('models', [ModelController::class, 'store']);
    Route::patch('models/{model}', [ModelController::class, 'update']);
    Route::delete('models/{model}', [ModelController::class, 'destroy']);

    Route::get('daily', [DayController::class, 'getDaily']);
    Route::post('daily', [DayController::class, 'store']);
});

Route::get('/validate', function () {
    $user = auth()->user();
    return response()->json([
        'message' => $user
    ]);
});


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);


Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/profile', function () {
        return response()->json(auth()->user());
    });
});
