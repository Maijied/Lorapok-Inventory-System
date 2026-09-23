<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellController;
use Illuminate\Support\Facades\Auth;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', [HomeController::class, 'index'])->name('home');
Auth::routes();
Route::get('/user/home', [HomeController::class, 'userHome'])->name('user.home');
Route::get('admin/home', [HomeController::class, 'adminHome'])->name('admin.home')->middleware('is_admin');

 Route::get('/product', [ProductController::class, 'index'])->name('product.index')->middleware('is_admin');
 Route::post('/product/store', [ProductController::class, 'store'])->name('product.store')->middleware('is_admin');
 Route::get('/product/edit/{id}', [ProductController::class, 'edit'])->name('product.edit')->middleware('is_admin');
 Route::post('/product/update/{id}', [ProductController::class, 'update'])->name('product.update')->middleware('is_admin');
 Route::post('/product/delete/{id}', [ProductController::class, 'delete'])->name('product.delete')->middleware('is_admin');
 
 Route::get('/sell', [SellController::class, 'index'])->name('sell.index')->middleware('is_admin');
 Route::get('/all/sells', [SellController::class, 'all_sells'])->name('all.sell.index')->middleware('is_admin');
 Route::post('/sell/store', [SellController::class, 'store'])->name('sell.store')->middleware('is_admin');
 Route::get('/sell/edit/{id}', [SellController::class, 'edit'])->name('sell.edit')->middleware('is_admin');
 Route::get('/sell/view/{id}', [SellController::class, 'view'])->name('sell.view')->middleware('is_admin');
 Route::get('/sell/invoice/{id}', [SellController::class, 'invoice'])->name('sell.invoice')->middleware('is_admin');
 Route::post('/sell/update/{id}', [SellController::class, 'update'])->name('sell.update')->middleware('is_admin');
 Route::post('/sell/delete/{id}', [SellController::class, 'delete'])->name('sell.delete')->middleware('is_admin');
 
 Route::get('/brand', [ProductController::class, 'brand_index'])->name('brand.index')->middleware('is_admin');
 Route::post('/brand/store', [ProductController::class, 'brand_store'])->name('brand.store')->middleware('is_admin');
 Route::get('/brand/edit/{id}', [ProductController::class, 'brand_edit'])->name('brand.edit')->middleware('is_admin');
 Route::post('/brand/update/{id}', [ProductController::class, 'brand_update'])->name('brand.update')->middleware('is_admin');
 Route::post('/brand/delete/{id}', [ProductController::class, 'brand_delete'])->name('brand.delete')->middleware('is_admin');
