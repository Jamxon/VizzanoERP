<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\ConstructorController;
use App\Http\Controllers\DepartmentController;
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
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('supervisor')->middleware('role:supervisor')->group(function () {
    Route::get('groups', [GroupController::class, 'index']);
    Route::post('groups', [GroupController::class, 'store']);
    Route::patch('groups/{group}', [GroupController::class, 'update']);
    Route::delete('groups/{group}', [GroupController::class, 'delete']);
    Route::get('fasteningOrderToGroup', [GroupController::class, 'fasteningOrderToGroup']);

    Route::get('users/master', [UserController::class, 'getUsersMaster']);
    Route::get('users/submaster', [UserController::class, 'getUsersSubMaster']);
    Route::get('users/warehouse', [WarehouseController::class, 'getWarehouseUsers']);

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
    Route::delete('models/image/{modelImage}', [ModelController::class, 'destroyImage']);

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

    Route::get('export-items', [ItemController::class, 'export']);

    Route::get('warehouses', [WarehouseController::class, 'getWarehouse']);
    Route::post('warehouses', [WarehouseController::class, 'warehouseStore']);
    Route::patch('warehouses/{warehouse}', [WarehouseController::class, 'warehouseUpdate']);

    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::patch('departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
});

Route::prefix('hr')->middleware('role:hr')->group(function () {
    Route::get('export-employees', [UserController::class, 'export']);
});

Route::prefix('omborchi')->middleware('role:omborchi')->group(function () {
    Route::get('export-items', [ItemController::class, 'export']);
});

Route::prefix('technologist')->middleware('role:technologist')->group(function () {
    Route::get('export-items', [ItemController::class, 'export']);
    Route::get('models', [ModelController::class, 'index']);
    Route::get('models/{model}', [ModelController::class, 'show']);
    Route::post('specification', [TechnologController::class, 'storeSpecification']);
    Route::get('specification/{submodelId}', [TechnologController::class, 'getSpecificationBySubmodelId']);
    Route::patch('specification/{id}', [TechnologController::class, 'updateSpecification']);
    Route::delete('specification/category/{id}', [TechnologController::class, 'destroySpecificationCategory']);
    Route::delete('specification/{id}', [TechnologController::class, 'destroySpecification']);

    Route::post('tarification', [TechnologController::class, 'storeTarification']);
    Route::patch('tarification/{id}', [TechnologController::class, 'updateTarification']);
    Route::get('tarification/users', [TechnologController::class, 'getEmployerByDepartment']);
    Route::get('tarification/orders', [TechnologController::class, 'getOrders']);
    Route::get('tarification/orders/{order}', [OrderController::class, 'show']);
    Route::get('tarification/show/{id}', [TechnologController::class, 'showTarification']);
    Route::get('tarification/{modelId}', [TechnologController::class, 'getTarificationByOrderModelId']);
    Route::get('tarification/category/{submodelId}', [TechnologController::class, 'getTarificationBySubmodelId']);
    Route::get('typewriter', [TechnologController::class, 'getTypeWriter']);
    Route::get('razryads', [RazryadController::class, 'index']);
    Route::delete('tarification/category/{id}', [TechnologController::class, 'destroyTarificationCategory']);
    Route::delete('tarification/{id}', [TechnologController::class, 'deleteTarification']);
    Route::post('tarification/fastening', [TechnologController::class, 'fasteningToEmployee']);
});


Route::prefix('constructor')->middleware('role:constructor')->group(function () {
    Route::get('getOrders', [ConstructorController::class, 'getOrderWithPlan']);
});
    Route::get('lids', [LidController::class, 'index']);
    Route::post('lids', [LidController::class, 'store']);
    Route::patch('lids/{lid}', [LidController::class, 'update']);
    Route::post('lids/search', [LidController::class, 'search']);

    Route::get('/validate', function () {
        return response()->json(['message' => auth()->user()], 200);
    })->middleware('validate.status');

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);