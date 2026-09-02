<?php

use App\Http\Controllers\JobListingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResumeDownloadController;
use Illuminate\Support\Facades\Route;

// No public landing page yet (Phase 7): guests go to login, users to their dashboard.
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

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

    // Job preferences (industry, arrangement, seniority, salary floor…)
    Route::view('/preferences', 'preferences.index')->name('preferences.index');

    // Job listings — browse/filter, then link out to the original posting
    Route::view('/jobs', 'jobs.index')->name('jobs.index');
    Route::get('/jobs/{jobListing}', [JobListingController::class, 'show'])->name('jobs.show');

    // Ranked matches with score breakdowns
    Route::view('/matches', 'matches.index')->name('matches.index');

    // Saved / applied jobs tracker
    Route::view('/applications', 'applications.index')->name('applications.index');

    // Advisory mode: persisted Skills Gap Plan
    Route::view('/plan', 'plan.index')->name('plan.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
