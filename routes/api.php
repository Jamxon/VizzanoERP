<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\DetalController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LidController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\ModelKartaController;
use App\Http\Controllers\NormalizationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RecipeController;
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
    Route::post('items', [ItemController::class, 'store']);
    Route::patch('items/{item}', [ItemController::class, 'update']);
    Route::get('items', [ItemController::class, 'index']);
    Route::post('recipes', [RecipeController::class, 'store']);
    Route::patch('recipes/{recipe}', [RecipeController::class, 'update']);
    Route::get('submodels', [\App\Http\Controllers\SubModelController::class, 'index']);
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
