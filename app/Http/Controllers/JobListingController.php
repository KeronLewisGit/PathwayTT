<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\JobMatch;
use App\Services\SalaryFormatter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobListingController extends Controller
{
    /** Job detail. Users always apply on the original posting (apply_url). */
    public function show(Request $request, JobListing $jobListing, SalaryFormatter $salary): View
    {
        $jobListing->load(['industry', 'skills', 'company']);

        return view('jobs.show', [
            'job' => $jobListing,
            'salaryLabel' => $salary->format($jobListing),
            'requiredSkills' => $jobListing->skills->where('pivot.is_required', true)->values(),
            'preferredSkills' => $jobListing->skills->where('pivot.is_required', false)->values(),
            'match' => JobMatch::query()
                ->where('user_id', $request->user()->id)
                ->where('job_listing_id', $jobListing->id)
                ->first(),
        ]);
    }
}
