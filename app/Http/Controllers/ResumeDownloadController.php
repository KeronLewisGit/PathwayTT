<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resumes live outside the public webroot and are served only through this
 * signed, policy-gated route (see docs/SPEC.md privacy requirements).
 */
class ResumeDownloadController extends Controller
{
    public function __invoke(Resume $resume): StreamedResponse
    {
        Gate::authorize('view', $resume);

        return Storage::disk(config('resume.disk'))->download(
            $resume->path,
            $resume->original_filename,
        );
    }
}
