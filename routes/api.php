<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\LidController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RazryadController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SubModelController;
use App\Http\Controllers\TechnologController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:supervisor')->group(function () {
    Route::get('groups', [GroupController::class, 'index']);
    Route::post('groups',[GroupController::class, 'store']);
    Route::patch('groups/{group}', [GroupController::class,'update']);
    Route::delete('groups/{group}',[GroupController::class, 'delete']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'delete']);
    Route::patch('changeorderstatus', [OrderController::class, 'changeOrderStatus']);
    Route::get('models', [ModelController::class, 'index']);
    Route::post('models', [ModelController::class, 'store']);
    Route::get('models/{model}', [ModelController::class, 'show']);
    Route::patch('models/{model}', [ModelController::class, 'update']);
    Route::delete('models/{model}', [ModelController::class, 'destroy']);
    Route::delete('model/image/{modelImage}', [ModelController::class, 'destroyImage']);
    Route::post('items', [ItemController::class, 'store']);
    Route::patch('items/{item}', [ItemController::class, 'update']);
    Route::get('items', [ItemController::class, 'index']);
    Route::get('itemtypes', [ItemTypeController::class, 'index']);
    Route::post('itemtypes/{itemType}', [ItemTypeController::class, 'store']);
    Route::patch('itemtypes/{itemType}', [ItemTypeController::class, 'update']);
    Route::delete('itemtypes/{itemType}', [ItemTypeController::class, 'destroy']);
    Route::get('recipes', [RecipeController::class, 'show']);
    Route::get('getrecipes', [RecipeController::class, 'getRecipe']);
    Route::post('recipes', [RecipeController::class, 'store']);
    Route::patch('recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy']);
    Route::get('submodels', [SubModelController::class, 'index']);
    Route::get('units', [UnitController::class, 'index']);
    Route::post('units', [UnitController::class, 'store']);
    Route::patch('units/{unit}', [UnitController::class, 'update']);
    Route::delete('units/{unit}', [UnitController::class, 'destroy']);
    Route::get('colors', [ColorController::class, 'index']);
    Route::post('colors', [ColorController::class, 'store']);
    Route::patch('colors/{color}', [ColorController::class, 'update']);
    Route::delete('colors/{color}', [ColorController::class, 'destroy']);
    Route::get('razryads', [RazryadController::class, 'index']);
    Route::post('razryads', [RazryadController::class, 'store']);
    Route::patch('razryads/{razryad}', [RazryadController::class, 'update']);
    Route::delete('razryads/{razryad}', [RazryadController::class, 'destroy']);
    Route::get('export-items/supervisor', [ItemController::class, 'export']);
});

Route::middleware('role:hr')->group(function (){
   Route::get('export-employees', [UserController::class, 'export']);
});

Route::middleware('role:omborchi')->group(function () {
    Route::get('export-items/omborchi', [ItemController::class, 'export']);
});

Route::middleware('role:technologist')->group(function () {
    Route::get('export-items/technologist', [ItemController::class, 'export']);
    Route::get('models/technologist', [ModelController::class, 'index']);
    Route::get('models/technologist/{model}', [ModelController::class, 'show']);
    Route::get('applications', [TechnologController::class, 'getApplication']);
    Route::get('applications/{model_id}',[TechnologController::class, 'getByModelId']);
    Route::post('applications', [TechnologController::class, 'storeApplication']);
    Route::patch('applications/{application}', [TechnologController::class, 'updateApplication']);
    Route::delete('applications/{application}', [TechnologController::class, 'destroy']);
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
