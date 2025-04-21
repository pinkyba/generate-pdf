<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleServiceGmail;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/generate-pdf', [PdfController::class, 'generatePdf']);

Route::get('/callback', [PdfController::class, 'handleCallback']);



