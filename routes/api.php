<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\PlanosController;

Route::post('/login',[AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/categoria/index',[CategoriaController::class,'index']);
    Route::post('/categoria',[CategoriaController::class,'store']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });


 Route::middleware(['role:admin'])->group(function(){
        Route::get('/admin-dashboard', function () {
            return  response()->json(['message'=>'Welcome Admin']);
        });

        Route::post('/plano',[PlanosController::class,'store']);
    });

 Route::middleware(['role:client'])->group(function(){
        Route::get('/seller-dashboard', function () {
            return  response()->json(['message'=>'Welcome Seller']);
        });
    });


  Route::post('/logout', [AuthController::class, 'logout']);


});