<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\PromptWeaverController;

Route::middleware('web')->group(function (): void {
    Route::get('/', [PromptWeaverController::class, 'index'])->name('workbench.prompt-weaver.index');
    Route::post('/prepare', [PromptWeaverController::class, 'prepare'])->name('workbench.prompt-weaver.prepare');
    Route::post('/generations/{generation}/image', [PromptWeaverController::class, 'upload'])->name('workbench.prompt-weaver.upload');
    Route::post('/generations/{generation}/calibrate', [PromptWeaverController::class, 'calibrate'])->name('workbench.prompt-weaver.calibrate');
    Route::get('/generations/{generation}/{asset}', [PromptWeaverController::class, 'asset'])->name('workbench.prompt-weaver.asset');
});
