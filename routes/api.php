<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CasherController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\ConstructorController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CuttingMasterController;
use App\Http\Controllers\CuttingPlanController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupHelperController;
use App\Http\Controllers\GroupMasterController;
use App\Http\Controllers\HikvisionEventController;
use App\Http\Controllers\InternalAccountantController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\PackageMasterController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\QualityControllerMasterController;
use App\Http\Controllers\RazryadController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ResultCheckerController;
use App\Http\Controllers\ShipmentPlanController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\SubModelController;
use App\Http\Controllers\SuperHRController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TailorController;
use App\Http\Controllers\TechnologController;
use App\Http\Controllers\TransportAttendanceController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VizzanoReportTvController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\TailorMasterController;
use App\Http\Controllers\EskizTestController;
use Illuminate\Support\Facades\Route;

Route::prefix('ceo')->middleware('role:ceo')->group(function () {
    Route::get('groupsOrders', [\App\Http\Controllers\CeoController::class, 'getGroupOrder']);
    Route::post('incomes', [CasherController::class, 'storeIncome']);
    Route::post('expenses', [CasherController::class, 'storeExpense']);
    Route::get('balances', [CasherController::class, 'getBalances']);
    Route::get('transactions', [CasherController::class, 'getTransactions']);
    Route::post('transfers', [CasherController::class, 'transferBetweenCashboxes']);
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::post('requestForm', [CasherController::class, 'storeRequestForm']);
    Route::get('via', [CasherController::class, 'getVia']);
    Route::get('sources', [CasherController::class, 'getSource']);
    Route::get('destinations', [CasherController::class, 'getDestination']);
    Route::get('employees', [SuperHRController::class, 'getEmployees']);
    Route::get('requests', [CasherController::class, 'getRequestForm']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);
    Route::post('groups', [CasherController::class, 'getGroupsByDepartmentId']);
    Route::post('groups2', [CasherController::class, 'getGroupsOrdersEarnings']);
    Route::post('groups2/excel', [CasherController::class, 'exportGroupsOrdersEarnings']);
    Route::get('orders', [CasherController::class, 'getOrders']);
    Route::post('salaries', [CasherController::class, 'giveSalaryOrAdvance']);
    Route::get('employees/{employee}', [UserController::class, 'showEmployee']);
    Route::get('employee/aup', [SuperHRController::class, 'getAupEmployee']);
    Route::get('positions' , [SuperHRController::class, 'getPositions']);
    Route::get('roles', [SuperHRController::class, 'getRoles']);
    Route::get('employee/edit/{id}', [SuperHRController::class, 'showEmployee']);
    Route::patch('employees/{employee}', [SuperHRController::class, 'updateEmployees']);
    Route::post('pdf/employees', [CasherController::class, 'exportGroupsByDepartmentId']);
    Route::get('dailyReport', [CasherController::class, 'getDailyCost']);
    Route::post('monthlyExpense', [CasherController::class, 'storeMonthlyExpense']);
    Route::get('monthlyExpense', [CasherController::class, 'getMonthlyExpense']);
    Route::patch('monthlyExpense/{id}', [CasherController::class, 'editMonthlyExpense']);
    Route::post('groupPlans', [CasherController::class, 'storeGroupPlan']);
    Route::get('groupPlans', [CasherController::class, 'getGroupPlans']);
    Route::patch('groupPlans/{id}', [CasherController::class, 'editGroupPlan']);
    Route::get('monthlyReport', [CasherController::class, 'getMonthlyCost']);
    Route::get('cuttingPlans', [CuttingPlanController::class, 'index']);
    Route::post('cuttingPlans', [CuttingPlanController::class, 'store']);
    Route::patch('cuttingPlans/{id}', [CuttingPlanController::class, 'update']);
    Route::delete('cuttingPlans/{id}', [CuttingPlanController::class, 'destroy']);
    Route::get('tarificationLogs', [InternalAccountantController::class, 'getEmployeeTarificationLog']);
    Route::get('orderSalary/{id}', [InternalAccountantController::class, 'getOrderAttendanceSalary']);
    Route::get('purposes', [CasherController::class, 'getLatestPurposes']);
    Route::get('comments', [CasherController::class, 'getLatestComments']);
    Route::get('sources', [CasherController::class, 'getLatestSources']);
    Route::get('/monthly-export/excel', [CasherController::class, 'exportMonthlyCostExcel']);
    Route::get('monthly-export/pdf', [CasherController::class, 'exportMonthlyCostPdf']);
    Route::get('export-attendance', [CasherController::class, 'exportEmployeeAttendance']);
    Route::get('monthlySelectedOrders', [CeoController::class, 'getMonthlySelectedOrders']);
    Route::post('monthlySelectedOrders', [CeoController::class, 'storeMonthlySelectedOrders']);
    Route::delete('monthlySelectedOrders/{id}', [CeoController::class, 'destroyMonthlySelectedOrders']);
    Route::patch('monthlySelectedOrders/{id}', [CeoController::class, 'updateMonthlySelectedOrders']);
});

Route::prefix('tailor')->middleware('role:tailor')->group(function () {
    Route::get('tarifications/search', [TailorController::class, 'searchTarifications']);
    Route::get('balance', [TailorController::class, 'getDailyBalanceEmployee']);
    Route::post('tarificationLog', [TailorController::class, 'storeTarificationLog']);
    Route::get('models', [TailorController::class, 'getModelWithTarification']);
    Route::get('packets', [TailorController::class, 'getTarificationPackets']);
    Route::post('packets', [TailorController::class, 'storeTarificationPackets']);
    Route::patch('packets/{id}', [TailorController::class, 'updateTarificationPacket']);
    Route::delete('packets/{id}', [TailorController::class, 'deleteTarificationPacket']);
    Route::delete('packets/item/{id}', [TailorController::class, 'deleteTarificationPacketItem']);
    Route::get('myWorks', [TailorController::class, 'getMyWorks']);
});

Route::prefix('resultChecker')->middleware('role:resultChecker')->group(function () {
    Route::get('groups', [ResultCheckerController::class, 'getGroups']);
    Route::get('times',[\App\Http\Controllers\GroupMasterController::class, 'getTimes']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);
    Route::post('sewingOutputs',[\App\Http\Controllers\GroupMasterController::class, 'SewingOutputsStore']);
    Route::get('employeeResults', [ResultCheckerController::class, 'getEmployeeByGroupId']);
    Route::post('employeeResults', [ResultCheckerController::class, 'storeEmployeeResult']);
    Route::get('employees', [SuperHRController::class, 'getEmployees']);
    Route::get('tarifications/search', [InternalAccountantController::class, 'searchTarifications']);
    Route::get('employeeStatistics', [ResultCheckerController::class, 'getDailyWorkStatistics']);
    Route::patch('employeeResults/{employeeResult}', [ResultCheckerController::class, 'updateEmployeeResult']);
});

Route::prefix('casher')->middleware('role:casher')->group(function () {
    Route::post('incomes', [CasherController::class, 'storeIncome']);
    Route::post('expenses', [CasherController::class, 'storeExpense']);
    Route::get('balances', [CasherController::class, 'getBalances']);
    Route::get('transactions', [CasherController::class, 'getTransactions']);
    Route::post('transfers', [CasherController::class, 'transferBetweenCashboxes']);
    Route::get('currencies', [CurrencyController::class, 'index']);
    Route::post('requestForm', [CasherController::class, 'storeRequestForm']);
    Route::get('via', [CasherController::class, 'getVia']);
    Route::get('sources', [CasherController::class, 'getSource']);
    Route::get('destinations', [CasherController::class, 'getDestination']);
    Route::get('employees', [SuperHRController::class, 'getEmployees']);
    Route::get('requests', [CasherController::class, 'getRequestForm']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);
    Route::post('groups', [CasherController::class, 'getGroupsByDepartmentId']);
    Route::get('orders', [CasherController::class, 'getOrders']);
    Route::post('salaries', [CasherController::class, 'giveSalaryOrAdvance']);
    Route::get('employees/{employee}', [UserController::class, 'showEmployee']);
    Route::get('employee/aup', [SuperHRController::class, 'getAupEmployee']);
    Route::get('positions' , [SuperHRController::class, 'getPositions']);
    Route::get('roles', [SuperHRController::class, 'getRoles']);
    Route::get('employee/edit/{id}', [SuperHRController::class, 'showEmployee']);
    Route::patch('employees/{employee}', [SuperHRController::class, 'updateEmployees']);
    Route::post('pdf/employees', [CasherController::class, 'exportGroupsByDepartmentId']);
    //    Route::get('dailyReport', [CasherController::class, 'getDailyCost']);
    Route::post('monthlyExpense', [CasherController::class, 'storeMonthlyExpense']);
    Route::get('monthlyExpense', [CasherController::class, 'getMonthlyExpense']);
    Route::patch('monthlyExpense/{id}', [CasherController::class, 'editMonthlyExpense']);
    Route::post('groupPlans', [CasherController::class, 'storeGroupPlan']);
    Route::get('groupPlans', [CasherController::class, 'getGroupPlans']);
    Route::patch('groupPlans/{id}', [CasherController::class, 'editGroupPlan']);
    //    Route::get('monthlyReport', [CasherController::class, 'getMonthlyCost']);
    Route::get('cuttingPlans', [CuttingPlanController::class, 'index']);
    Route::post('cuttingPlans', [CuttingPlanController::class, 'store']);
    Route::patch('cuttingPlans/{id}', [CuttingPlanController::class, 'update']);
    Route::delete('cuttingPlans/{id}', [CuttingPlanController::class, 'destroy']);
    Route::get('purposes', [CasherController::class, 'getLatestPurposes']);
    Route::get('comments', [CasherController::class, 'getLatestComments']);
    Route::get('sources', [CasherController::class, 'getLatestSources']);
});

Route::prefix('supplier')->middleware('role:supplier')->group(function () {
    Route::get('orders', [SupplierController::class, 'getOrders']);
    Route::get('receive/{id}', [SupplierController::class, 'receiveSupplierOrder']);
});

Route::prefix('internalAccountant')->middleware('role:internalAccountant')->group(function () { 
    Route::get('employees', [SuperHRController::class, 'getEmployees']);
    //    Route::get('employees/group', [SuperHRController::class, 'getEmployeeByGroupID']);
    Route::get('employees/working', [SuperHRController::class, 'getWorkingEmployees']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);
    Route::get('attendances', [AttendanceController::class, 'getAttendances']);
    Route::get('attendances/history', [AttendanceController::class, 'getAttendanceHistory']);
    Route::post('attendances', [AttendanceController::class, 'storeAttendance']);
    Route::patch('attendances/{attendance}', [AttendanceController::class, 'updateAttendance']);
    Route::get('orders', [InternalAccountantController::class, 'getOrders']);
    Route::get('orders/{order}', [InternalAccountantController::class, 'showOrder']);
    Route::get('tarifications/search', [InternalAccountantController::class, 'searchTarifications']);
    Route::get('tarifications/show', [InternalAccountantController::class, 'showTarifications']);
    Route::patch('tarification/{id}', [TechnologController::class, 'updateTarification']);
    Route::get('tarifications', [InternalAccountantController::class, 'getTarifications']);
    Route::get('users', [TechnologController::class, 'getEmployerByDepartment']);
    Route::get('tarification/{id}', [TechnologController::class, 'showTarificationCategory']);
    Route::get('dailyPlan', [InternalAccountantController::class, 'generateDailyPlan']);
    Route::get('dailyPlan/employee', [InternalAccountantController::class, 'generateDailyPlanForOneEmployee']);
    Route::get('dailyPlan/{id}', [InternalAccountantController::class, 'showDailyPlan']);
    Route::post('employeeSalaryCalculation', [InternalAccountantController::class, 'employeeSalaryCalculation']);
    Route::get('boxTarifications/{boxTarification}', [InternalAccountantController::class, 'boxTarificationShow']);
    Route::post('salaryCalculate', [InternalAccountantController::class, 'salaryCalculation']);
    Route::get('tarificationLogs', [InternalAccountantController::class, 'getEmployeeTarificationLog']);
    Route::get('nakladnoy', [InternalAccountantController::class, 'generateAttendanceNakladnoy']);
    Route::get('groups', [InternalAccountantController::class, 'getGroups']);
    Route::patch('employeeTarificationLog/{id}', [InternalAccountantController::class, 'updateEmployeeTarificationLog']);
    Route::get('tarifications/pdf', [TechnologController::class, 'exportTarificationsPdf']);
    Route::get('employees/{employee}', [UserController::class, 'showEmployee']);
    Route::get('tarifSearch', [InternalAccountantController::class, 'getModelByTarificationCode']);
    Route::get('orderSalary/{id}', [InternalAccountantController::class, 'getOrderAttendanceSalary']);

});

Route::prefix('warehouseManager')->middleware('role:warehouseManager')->group(function () {
    Route::post('/incoming', [WarehouseController::class, 'storeIncoming']);
    Route::get('/incoming', [WarehouseController::class, 'getIncoming']);
    Route::post('/outcome', [WarehouseController::class, 'storeOutcome']);
    Route::get('/outcome', [WarehouseController::class, 'getOutcome']);
    Route::get('/balances', [WarehouseController::class, 'getBalance']);
    Route::get('balances/pdf', [WarehouseController::class, 'exportStockBalancesPdf']);
    Route::get('items', [ItemController::class, 'index']);
    Route::get('items/search', [ItemController::class, 'search']);
    Route::post('items', [ItemController::class, 'store']);
    Route::get('items/{item}', [ItemController::class, 'show']);
    Route::patch('items/{item}', [ItemController::class, 'update']);
    Route::get('items-export', [ItemController::class, 'export']);
    Route::get('colors',[ColorController::class, 'index']);
    Route::post('colors', [ColorController::class, 'store']);
    Route::patch('colors/{color}', [ColorController::class, 'update']);
    Route::get('types',[ItemTypeController::class, 'index']);
    Route::get('units',[UnitController::class, 'index']);
    Route::get('currencies',[CurrencyController::class, 'index']);
    Route::get('sources',[SourceController::class, 'index']);
    Route::get('warehouses', [WarehouseController::class, 'getWarehouses']);
    Route::get('orders', [WarehouseController::class, 'getOrders']);
    Route::get('orders/{order}', [WarehouseController::class, 'showOrder']);
    Route::get('contragents', [WarehouseController::class, 'getContragents']);
    Route::get('destinations', [WarehouseController::class, 'getDestinations']);
    Route::get('stockEntry/{id}', [WarehouseController::class, 'downloadPdf']);
    Route::get('users', [WarehouseController::class, 'getUsers']);
    Route::post('supplierOrders', [SupplierController::class, 'store']);
    Route::get('supplierOrders', [SupplierController::class, 'getSupplierOrder']);
    Route::delete('supplierOrders/{id}', [SupplierController::class, 'destroySupplierOrder']);
    Route::get('suppliers', [SupplierController::class, 'getSupplier']);
});

Route::prefix('transport')->middleware('role:transport')->group(function () {
    Route::get('transports', [TransportController::class, 'index']);
    Route::get('transports/{id}', [TransportController::class, 'show']);
    Route::get('transports/show/{transport}', [TransportController::class, 'transportShow']);
    Route::post('transports', [TransportController::class, 'store']);
    Route::patch('transports/{id}', [TransportController::class, 'update']);

    Route::get('attendances', [TransportAttendanceController::class, 'index']);
    Route::post('attendances', [TransportAttendanceController::class, 'store']);
    Route::patch('attendances/{id}', [TransportAttendanceController::class, 'update']);
    Route::post('massStore', [TransportAttendanceController::class, 'massStore']);
    Route::post('massStoreByDates', [TransportAttendanceController::class, 'massStoreByDates']);
    Route::post('payment', [TransportAttendanceController::class, 'storeTransaction']);
    Route::patch('payment/{id}', [TransportAttendanceController::class, 'updateTransaction']);
    Route::get('regions', [SuperHRController::class, 'getRegions']);

});

Route::prefix('packageMaster')->middleware('role:packageMaster')->group(function () {
    Route::get('orders', [PackageMasterController::class, 'getOrders']);
    Route::get('orders/{order}', [PackageMasterController::class, 'showOrder']);
    Route::post('packageStore', [PackageMasterController::class, 'packageStore']);
    Route::get('shipmentPlans', [ShipmentPlanController::class, 'index']);
    Route::post('shipmentPlans', [ShipmentPlanController::class, 'store']);
    Route::patch('shipmentPlans/{shipmentPlan}', [ShipmentPlanController::class, 'update']);
    Route::patch('shipmentPlans/status/{shipmentPlan}', [ShipmentPlanController::class, 'editStatus']);
});

Route::prefix('qualityControllerMaster')->middleware('role:qualityControllerMaster')->group(function () {
    Route::get('orders',[QualityControllerMasterController::class,'getOrders']);
    Route::get('results', [QualityControllerMasterController::class, 'results']);
    Route::post('fasteningOrderToGroup', [QualityControllerMasterController::class, 'fasteningOrderToGroup']);
    Route::get('groups',[QualityControllerMasterController::class, 'getGroups']);
    Route::post('changeOrderStatus',[QualityControllerMasterController::class, 'changeOrderStatus']);
});

Route::prefix('qualityController')->middleware('role:qualityController')->group(function () {
    Route::get('orders',[QualityController::class, 'getOrders']);
    Route::get('orders/{id}',[QualityController::class, 'showOrder']);
    Route::post('qualitySuccessCheck',[QualityController::class, 'qualityCheckSuccessStore']);
    Route::post('qualityFailureCheck',[QualityController::class, 'qualityCheckFailureStore']);
    Route::get('qualityCheck',[QualityController::class, 'getQualityChecks']);
});

Route::prefix('groupMaster')->middleware('role:groupMaster')->group(function (){
       Route::get('orders',[\App\Http\Controllers\GroupMasterController::class, 'getOrders']);
       Route::get('orders/all', [GroupMasterController::class, 'getOrdersAll']);
       Route::get('orders/all/{id}', [GroupMasterController::class, 'showOrdersAll']);
       Route::get('orders/pending',[\App\Http\Controllers\GroupMasterController::class, 'getPendingOrders']);
       Route::get('orders/show/{id}',[\App\Http\Controllers\GroupMasterController::class, 'showOrder']);
       Route::get('employees',[\App\Http\Controllers\GroupMasterController::class, 'getEmployees']);
       Route::get('tarifications/{id}',[\App\Http\Controllers\GroupMasterController::class, 'getTarifications']);
       Route::post('tarifications',[\App\Http\Controllers\GroupMasterController::class,'assignEmployeesToTarifications']);
       Route::get('times',[\App\Http\Controllers\GroupMasterController::class, 'getTimes']);
       Route::post('sewingOutputs',[\App\Http\Controllers\GroupMasterController::class, 'SewingOutputsStore']);
       Route::get('orderCuts',[\App\Http\Controllers\GroupMasterController::class, 'getOrderCuts']);
       Route::get('orderCuts/show',[\App\Http\Controllers\GroupMasterController::class, 'showOrderCuts']);
       Route::post('orderCuts/{id}',[\App\Http\Controllers\GroupMasterController::class, 'receiveOrderCut']);
       Route::get('plans',[GroupMasterController::class, 'getPlans']);
       Route::post('receiveOrder',[GroupMasterController::class, 'receiveOrder']);
       Route::get('orders2/{order}',[InternalAccountantController::class, 'showOrder']);
       Route::get('users2', [TechnologController::class, 'getEmployerByDepartment']);
       Route::get('dailyPlan', [InternalAccountantController::class, 'generateDailyPlan']);
       Route::get('dailyPlan/employee', [InternalAccountantController::class, 'generateDailyPlanForOneEmployee']);
       Route::patch('tarification/{id}', [TechnologController::class, 'updateTarification']);
       Route::get('tarification/{id}', [TechnologController::class, 'showTarificationCategory']);
       Route::get('topEarners', [GroupMasterController::class, 'getTopEarners']);
});

Route::prefix('groupHelper')->middleware('role:groupHelper')->group(function () {
   Route::get('orders', [GroupHelperController::class, 'getOrders']);
    Route::get('orders/pending',[GroupMasterController::class, 'getPendingOrders']);
    Route::post('receiveOrder',[GroupHelperController::class, 'receiveOrder']);
    Route::get('times',[GroupMasterController::class, 'getTimes']);
    Route::post('exampleOutputs', [GroupHelperController::class, 'storeExampleOutput']);
    Route::get('orders/show/{id}',[GroupHelperController::class, 'showOrder']);

});

Route::prefix('supervisor')->middleware('role:supervisor')->group(function () {
    Route::post('groups', [GroupController::class, 'store']);
    Route::patch('groups/{group}', [GroupController::class, 'update']);
    Route::delete('groups/{group}', [GroupController::class, 'delete']);

    Route::get('plans', [GroupController::class, 'getGroupsWithPlan']);
    Route::post('plans', [GroupController::class,'orderGroupStore']);
    Route::get('orders/groups', [OrderController::class, 'getOrdersWithoutOrderGroups']);
    Route::get('orders/quantity', [OrderController::class, 'getOrdersWithQuantity']);

    Route::get('contragents', [OrderController::class, 'getContragents']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'delete']);
    Route::patch('orders/change/{order}', [OrderController::class, 'changeOrderStatus']);
    Route::get('/orders/{id}/pdf', [OrderController::class, 'generateOrderPdf']);

    Route::post('orderStore',[OrderImportController::class,'store']);


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

    Route::get('recipes', [RecipeController::class, 'show']);
    Route::get('getrecipes', [RecipeController::class, 'getRecipe']);
    Route::post('recipes', [RecipeController::class, 'store']);
    Route::patch('recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy']);

    Route::get('submodels', [SubModelController::class, 'index']);

    Route::get('units', [UnitController::class, 'index']);
    Route::post('units', [UnitController::class, 'store']);
    Route::patch('units/{unit}', [UnitController::class, 'update']);

    Route::get('colors', [ColorController::class, 'index']);
    Route::post('colors', [ColorController::class, 'store']);
    Route::patch('colors/{color}', [ColorController::class, 'update']);

    Route::get('razryads', [RazryadController::class, 'index']);
    Route::post('razryads', [RazryadController::class, 'store']);
    Route::patch('razryads/{razryad}', [RazryadController::class, 'update']);
    Route::delete('razryads/{razryad}', [RazryadController::class, 'destroy']);

    Route::get('warehouses', [WarehouseController::class, 'getWarehouses']);
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::patch('departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
});

Route::prefix('superhr')->middleware('role:superhr')->group(function () {
    Route::get('export-attendance', [CasherController::class, 'exportEmployeeAttendance']);
    Route::get('employees/aup', [SuperHRController::class, 'getAupEmployee']);
    Route::get('positions' , [SuperHRController::class, 'getPositions']);
    Route::post('positions' , [SuperHRController::class, 'storePositions']);
    Route::patch('positions/{position}', [SuperHRController::class, 'updatePositions']);
    Route::get('roles', [SuperHRController::class, 'getRoles']);
    Route::get('/employees/export-excel', [SuperHRController::class, 'exportToExcel']);
    Route::post('resetPassword/{id}', [SuperHRController::class, 'resetPassword']);
    Route::get('employees', [SuperHRController::class, 'getEmployees']);
    Route::get('employees/attendances/export', [SuperHRController::class, 'exportEmployeeAttendance']);
    Route::get('employees/working', [SuperHRController::class, 'getWorkingEmployees']);
    Route::get('employees/{id}', [SuperHRController::class, 'showEmployee']);
    Route::post('employees', [SuperHRController::class, 'storeEmployees']);
    Route::patch('employees/{employee}', [SuperHRController::class, 'updateEmployees']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);
    Route::post('departments', [SuperHRController::class, 'storeDepartments']);
    Route::patch('departments/{department}', [SuperHRController::class, 'updateDepartments']);
    Route::get('regions', [SuperHRController::class, 'getRegions']);
    Route::get('attendances', [AttendanceController::class, 'getAttendances']);
    Route::get('attendances/history', [AttendanceController::class, 'getAttendanceHistory']);
    Route::post('attendances', [AttendanceController::class, 'storeAttendance']);
    Route::patch('attendances/{attendance}', [AttendanceController::class, 'updateAttendance']);
    Route::post('employees/store', [SuperHRController::class, 'storeFastEmployee']);
    Route::post('groups', [GroupController::class, 'store']);
    Route::patch('groups/{group}', [GroupController::class, 'update']);
    Route::get('groups/{group}', [GroupController::class, 'show']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::get('employees/casher/{employee}', [UserController::class, 'showEmployee']);
    Route::get('filterAttendance', [SuperHRController::class, 'filterAttendance']);
    Route::get('export-attendance', [SuperHRController::class, 'exportAttendancePdf']);
    Route::post('employeeHolidays', [SuperHRController::class, 'storeEmployeeHoliday']);
    Route::get('employeeHolidays', [SuperHRController::class, 'getEmployeeHolidays']);
    Route::patch('employeeHolidays/{id}', [SuperHRController::class, 'editEmployeeHoliday']);
    Route::get('yesterdayAbsent',[SuperHRController::class, 'getYesterdayAbsent']);
    Route::get('todayAbsent',[SuperHRController::class, 'getPotentialAbsents']);
    Route::post('employeeAbsence', [SuperHRController::class, 'storeEmployeeAbsence']);
    Route::get('lids', [SuperHRController::class, 'getLids']);
    Route::post('lids', [SuperHRController::class, 'storeLid']);
    Route::patch('lids/{lid}', [SuperHRController::class, 'updateLid']);
    Route::get('lids/{lid}', [SuperHRController::class, 'showLid']);
});

Route::prefix('technologist')->middleware('role:technologist')->group(function () {
    Route::get('export-tarification',[TechnologController::class,'exportTarification']);
    Route::post('import-tarification',[TechnologController::class,'importTarifications']);
    Route::get('export-specification',[TechnologController::class,'exportSpecification']);
    Route::post('import-specification',[TechnologController::class,'importSpecification']);
    Route::get('tarifications/pdf', [TechnologController::class, 'exportTarificationsPdf']);
    Route::get('tarification/pdf', [TechnologController::class, 'exportPdf']);
    Route::get('export-items', [ItemController::class, 'export']);
    Route::get('models', [ModelController::class, 'index']);
    Route::get('models/{model}', [ModelController::class, 'show']);
    Route::post('specification', [TechnologController::class, 'storeSpecification']);
    Route::get('specification/{submodelId}', [TechnologController::class, 'getSpecificationBySubmodelId']);
    Route::get('specification/category/{id}', [TechnologController::class, 'showSpecificationCategory']);
    Route::patch('specification/{id}', [TechnologController::class, 'updateSpecification']);
    Route::delete('specification/category/{id}', [TechnologController::class, 'destroySpecificationCategory']);
    Route::delete('specification/{id}', [TechnologController::class, 'destroySpecification']);

    Route::post('tarification', [TechnologController::class, 'storeTarification']);
    Route::patch('tarification/{id}', [TechnologController::class, 'updateTarification']);
    Route::get('users', [TechnologController::class, 'getEmployerByDepartment']);
    Route::get('orders', [TechnologController::class, 'getOrders']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::get('tarification/show/{id}', [TechnologController::class, 'showTarification']);
    Route::get('tarification/{id}', [TechnologController::class, 'showTarificationCategory']);
    Route::get('tarification/category/{submodelId}', [TechnologController::class, 'getTarificationBySubmodelId']);
    Route::get('typewriter', [TechnologController::class, 'getTypeWriter']);
    Route::post('typewriter', [TechnologController::class, 'storeTypeWriter']);
    Route::patch('typewriter/{id}', [TechnologController::class, 'updateTypeWriter']);
    Route::get('razryads', [RazryadController::class, 'index']);
    Route::delete('tarification/category/{id}', [TechnologController::class, 'destroyTarificationCategory']);
    Route::delete('tarification/{id}', [TechnologController::class, 'deleteTarification']);
    Route::post('tarification/fastening', [TechnologController::class, 'fasteningToEmployee']);
    Route::get('confirmOrder', [TechnologController::class, 'confirmOrder']);
    Route::get('lastTarifications', [TechnologController::class, 'getLastTarifications']);
    Route::get('nakladnoy', [InternalAccountantController::class, 'generateAttendanceNakladnoy']);
    Route::get('groups', [InternalAccountantController::class, 'getGroups']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);

});

Route::prefix('constructor')->middleware('role:constructor')->group(function () {
    Route::get('orders', [ConstructorController::class, 'getOrders']);
    Route::get('orders/{id}', [ConstructorController::class, 'showOrder']);
    Route::post('orderPrintingTimes/{id}', [ConstructorController::class, 'sendToCuttingMaster']);
    Route::post('specification', [TechnologController::class, 'storeSpecification']);
    Route::patch('specification/{id}', [TechnologController::class, 'updateSpecification']);
    Route::get('specification/category/{id}', [TechnologController::class, 'showSpecificationCategory']);
    Route::get('export-specification',[TechnologController::class,'exportSpecification']);
});

Route::prefix('cuttingMaster')->middleware('role:cuttingMaster')->group(function () {
    Route::get('orders',[CuttingMasterController::class, 'getOrders']);
    Route::post('sendToConstructor', [CuttingMasterController::class, 'sendToConstructor']);
    Route::get('orders/{order}', [CuttingMasterController::class, 'showOrder']);
    Route::get('completedItems', [CuttingMasterController::class, 'getCompletedItems']);
    Route::get('specifications/{id}', [CuttingMasterController::class, 'getSpecificationByOrderId']);
    Route::get('markAsCut', [CuttingMasterController::class, 'markAsCutAndExportMultiplePdfs']);
    Route::get('cuts/{id}', [CuttingMasterController::class, 'getCuts']);
    Route::get('finishCutting/{id}', [CuttingMasterController::class, 'finishCutting']);
    Route::get('markAsCut/{boxTarification}', [CuttingMasterController::class, 'updateBoxTarification']);
    Route::get('cuttingPlans', [CuttingPlanController::class, 'index']);

});

Route::prefix('tv')->middleware('role:tv')->group(function () {
    Route::get('sewingOutputs', [VizzanoReportTvController::class, 'getSewingOutputs']);
});

Route::prefix('orderManager')->middleware('role:orderManager')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'delete']);
    Route::patch('orders/change/{order}', [OrderController::class, 'changeOrderStatus']);


    Route::get('contragents', [OrderController::class, 'getContragents']);
    Route::post('contragents', [OrderController::class, 'storeContragents']);
    Route::patch('contragents/{contragent}', [OrderController::class, 'updateContragents']);

    Route::get('models', [ModelController::class, 'index']);
    Route::post('models', [ModelController::class, 'store']);
    Route::get('models/{model}', [ModelController::class, 'show']);
    Route::patch('models/{model}', [ModelController::class, 'update']);
    Route::delete('models/{model}', [ModelController::class, 'destroy']);
    Route::delete('models/image/{modelImage}', [ModelController::class, 'destroyImage']);

    Route::get('materials', [ModelController::class, 'getMaterials']);

    Route::post('/import-orders', [OrderImportController::class, 'import']);

    Route::post('orderStore',[OrderImportController::class,'store']);
    Route::get('colors', [ColorController::class, 'index']);

});

Route::get('/validate', function () {
    return response()->json(['message' => auth()->user()], 200);
})->middleware('validate.status');

Route::middleware('auth:api')->group(function () {
    Route::get('profile', [UserController::class, 'getProfile']);
    Route::patch('profile/{employee}', [UserController::class, 'updateProfile']);
    Route::get('sewingOutputs', [VizzanoReportTvController::class, 'getSewingOutputs']);
    Route::get('groupPlans', [CasherController::class, 'getGroupPlans']);
    Route::post('issue', [UserController::class, 'storeIssue']);
    Route::post('sendSMS', [EskizTestController::class, 'sendSMS']);
    Route::get('departments', [SuperHRController::class, 'getDepartments']);
    Route::get('topEarners', [TailorController::class, 'getTopEarners']);
    Route::get('shipmentPlans', [ShipmentPlanController::class, 'getShipmentActive']);

});

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('logs',[OrderController::class, 'getLogs']);
Route::get('tvResult/{id}', [GroupMasterController::class, 'tvResult']);

Route::post('/hikvision/event', [HikvisionEventController::class, 'handleEvent']);