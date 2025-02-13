<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\ConstructorController;
use App\Http\Controllers\CuttingMasterController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RazryadController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SubModelController;
use App\Http\Controllers\TechnologController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VizzanoReportTvController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\TailorMasterController;
use Illuminate\Support\Facades\Route;

Route::prefix('groupMaster')->middleware('role:groupMaster')->group(function (){
   Route::get('orders',[\App\Http\Controllers\GroupMasterController::class, 'getOrders']);
   Route::get('orders/show/{id}',[\App\Http\Controllers\GroupMasterController::class, 'showOrder']);
    Route::get('orders/{id}',[\App\Http\Controllers\GroupMasterController::class,'startOrder']);
    Route::get('employees',[\App\Http\Controllers\GroupMasterController::class, 'getEmployees']);
    Route::get('tarifications/{id}',[\App\Http\Controllers\GroupMasterController::class, 'getTarifications']);
    Route::post('tarifications',[\App\Http\Controllers\GroupMasterController::class,'assignEmployeesToTarifications']);
    Route::get('times',[\App\Http\Controllers\GroupMasterController::class, 'getTimes']);
    Route::post('sewingOutputs',[\App\Http\Controllers\GroupMasterController::class, 'SewingOutputsStore']);
    Route::get('orderCuts',[\App\Http\Controllers\GroupMasterController::class, 'getOrderCuts']);
    Route::get('orderCuts/show',[\App\Http\Controllers\GroupMasterController::class, 'showOrderCuts']);
    Route::post('orderCuts',[\App\Http\Controllers\GroupMasterController::class, 'receiveOrderCut']);
});

Route::prefix('tailorMaster')->middleware('role:tailorMaster')->group(function () {
    Route::get('orders', [TailorMasterController::class, 'getOrders']);
    Route::get('groups', [TailorMasterController::class,'getGroups']);
    Route::post('sendToConstructor', [TailorMasterController::class, 'sendToConstructor']);
    Route::get('orders/{order}', [TailorMasterController::class, 'showOrder']);
    Route::get('completedItems', [TailorMasterController::class, 'getCompletedItems']);
    Route::post('completedItem', [TailorMasterController::class, 'acceptCompletedItem']);
    Route::get('specifications/{id}', [TailorMasterController::class, 'getSpecificationByOrderId']);
    Route::post('markAsTailored', [TailorMasterController::class, 'markAsTailored']);
    Route::get('cuts/{id}', [TailorMasterController::class, 'getCuts']);
    Route::post('fasteningOrderToGroup',[TailorMasterController::class, 'fasteningOrderToGroup']);
});

Route::prefix('supervisor')->middleware('role:supervisor')->group(function () {
    Route::post('groups', [GroupController::class, 'store']);
    Route::patch('groups/{group}', [GroupController::class, 'update']);
    Route::delete('groups/{group}', [GroupController::class, 'delete']);

    Route::get('users/master', [UserController::class, 'getUsersMaster']);
    Route::get('users/submaster', [UserController::class, 'getUsersSubMaster']);
    Route::get('users/warehouse', [WarehouseController::class, 'getWarehouseUsers']);

    Route::get('contragents', [OrderController::class, 'getContragents']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'delete']);
    Route::patch('orders/change/{order}', [OrderController::class, 'changeOrderStatus']);

    Route::get('materials', [ModelController::class, 'getMaterials']);

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
    Route::get('users', [TechnologController::class, 'getEmployerByDepartment']);
    Route::get('orders', [TechnologController::class, 'getOrders']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::get('tarification/show/{id}', [TechnologController::class, 'showTarification']);
    Route::get('tarification/{orderModelId}', [TechnologController::class, 'getTarificationByOrderModelId']);
    Route::get('tarification/category/{submodelId}', [TechnologController::class, 'getTarificationBySubmodelId']);
    Route::get('typewriter', [TechnologController::class, 'getTypeWriter']);
    Route::get('razryads', [RazryadController::class, 'index']);
    Route::delete('tarification/category/{id}', [TechnologController::class, 'destroyTarificationCategory']);
    Route::delete('tarification/{id}', [TechnologController::class, 'deleteTarification']);
    Route::post('tarification/fastening', [TechnologController::class, 'fasteningToEmployee']);
});


Route::prefix('constructor')->middleware('role:constructor')->group(function () {
    Route::get('orders', [ConstructorController::class, 'getOrders']);
    Route::get('orders/{id}', [ConstructorController::class, 'showOrder']);
    Route::post('orderPrintingTimes/{id}', [ConstructorController::class, 'sendToCuttingMaster']);
});

Route::prefix('cuttingMaster')->middleware('role:cuttingMaster')->group(function () {
    Route::get('orders',[CuttingMasterController::class, 'getOrders']);
    Route::post('sendToConstructor', [CuttingMasterController::class, 'sendToConstructor']);
    Route::get('orders/{order}', [CuttingMasterController::class, 'showOrder']);
    Route::get('completedItems', [CuttingMasterController::class, 'getCompletedItems']);
    Route::post('completedItem', [CuttingMasterController::class, 'acceptCompletedItem']);
    Route::get('specifications/{id}', [CuttingMasterController::class, 'getSpecificationByOrderId']);
    Route::post('markAsCut', [CuttingMasterController::class, 'markAsCut']);
    Route::get('cuts/{id}', [CuttingMasterController::class, 'getCuts']);
    Route::get('finishCutting/{id}', [CuttingMasterController::class, 'finishCutting']);
});

Route::get('sewingOutputs', [VizzanoReportTvController::class, 'getSewingOutputs']);

    Route::get('/validate', function () {
        return response()->json(['message' => auth()->user()], 200);
    })->middleware('validate.status');

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);