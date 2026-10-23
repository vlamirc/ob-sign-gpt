<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\SigningController;
use App\Http\Middleware\PrivateResponse;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::middleware(['auth', 'verified', PrivateResponse::class])->group(function (): void {
    Route::livewire('dashboard', 'pages::documents.index')->name('dashboard');
    Route::livewire('documentos/novo', 'pages::documents.create')->name('documents.create');
    Route::livewire('documentos/{document}', 'pages::documents.show')->name('documents.show');
    Route::get('documentos/{document}/original', [DocumentDownloadController::class, 'original'])->name('documents.original');
    Route::get('documentos/{document}/evidencias', [DocumentDownloadController::class, 'evidence'])->name('documents.evidence');
});
Route::middleware([PrivateResponse::class, 'throttle:60,1'])->group(function (): void {
    Route::get('convite/{token}', [SigningController::class, 'invite'])->where('token', '[A-Za-z0-9]{64}')->name('signing.invite');
    Route::get('assinar/{signer}', [SigningController::class, 'show'])->name('signing.show');
    Route::post('assinar/{signer}/codigo', [SigningController::class, 'code'])->middleware('throttle:signing-code')->name('signing.code');
    Route::post('assinar/{signer}/verificar', [SigningController::class, 'verify'])->middleware('throttle:signing-verify')->name('signing.verify');
    Route::get('assinar/{signer}/pdf', [SigningController::class, 'pdf'])->name('signing.pdf');
    Route::post('assinar/{signer}/aceitar', [SigningController::class, 'accept'])->middleware('throttle:signing-accept')->name('signing.accept');
});
require __DIR__.'/settings.php';
