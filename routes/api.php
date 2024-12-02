<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\DetalController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LidController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\ModelKartaController;
use App\Http\Controllers\NormalizationController;
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
    Route::patch('changeorderstatus', [OrderController::class, 'changeOrderStatus']);
    Route::get('models', [ModelController::class, 'index']);
    Route::post('models', [ModelController::class, 'store']);
    Route::patch('models/{model}', [ModelController::class, 'update']);
    Route::delete('models/{model}', [ModelController::class, 'destroy']);
    Route::get('normalizations', [NormalizationController::class, 'index']);
    Route::post('normalizations', [NormalizationController::class, 'store']);
    Route::patch('normalizations/{normalization}', [NormalizationController::class, 'update']);
    Route::delete('normalizations/{normalization}', [NormalizationController::class, 'destroy']);
    Route::get('modelkarta', [ModelKartaController::class, 'index']);
    Route::post('modelkarta', [ModelKartaController::class, 'store']);
    Route::patch('modelkarta/{model}', [ModelKartaController::class, 'update']);
    Route::delete('modelkarta/{model}', [ModelKartaController::class, 'destroy']);

    Route::get('daily', [DayController::class, 'getDaily']);
    Route::post('daily', [DayController::class, 'store']);
});

Route::middleware('role:technologist')->group(function () {
    Route::get('detals', [DetalController::class, 'index']);
    Route::post('detals', [DetalController::class, 'store']);
    Route::patch('detals/{detal}', [DetalController::class, 'update']);
    Route::delete('detals/{detal}', [DetalController::class, 'delete']);
    Route::get('detals/{id}', [DetalController::class, 'sortByModel']);
});

Route::get('lids', [LidController::class, 'index']);
Route::post('lids', [LidController::class, 'store']);
Route::patch('lids/{lid}', [LidController::class, 'update']);
Route::post('lids/search', [LidController::class, 'search']);

Route::get('/validate', function () {
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized: Token is invalid or missing'
        ], 401);
    }

    if (!$user->employee) {
        return response()->json([
            'message' => 'Unauthorized: Employee data is missing'
        ], 401);
    }

    if ($user->employee->status == "kicked") {
        return response()->json([
            'message' => 'You are kicked from the company'
        ], 401);
    }

    return response()->json([
        'message' => $user
    ], 200);
});


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);


Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/profile', function () {
        return response()->json(auth()->user());
    });
});
