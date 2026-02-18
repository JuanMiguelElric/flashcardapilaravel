<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;


Route::post('/login',[AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/user', function (Request $request) {
        return $request->user();
    });


 Route::middleware(['role:admin'])->group(function(){
        Route::get('/admin-dashboard', function () {
            return  response()->json(['message'=>'Welcome Admin']);
        });
    });

 Route::middleware(['role:seller'])->group(function(){
        Route::get('/seller-dashboard', function () {
            return  response()->json(['message'=>'Welcome Seller']);
        });
    });


  Route::post('/logout', [AuthController::class, 'logout']);


});