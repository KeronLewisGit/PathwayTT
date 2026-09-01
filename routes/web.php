<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResumeDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Resume upload + parse status
    Route::view('/resume', 'resume.index')->name('resume.index');

    // Editable review of everything the parser extracted
    Route::view('/profile/review', 'profile-review.index')->name('profile.review');

    // Resumes are private PII: signed, policy-gated download only
    Route::get('/resumes/{resume}/download', ResumeDownloadController::class)
        ->middleware('signed')
        ->name('resumes.download');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
