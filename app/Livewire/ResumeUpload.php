<?php

namespace App\Livewire;

use App\Enums\ParseStatus;
use App\Jobs\ParseResumeJob;
use App\Livewire\Concerns\AwardsAchievements;
use App\Models\Resume;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ResumeUpload extends Component
{
    use AwardsAchievements;
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    protected function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('resume.max_size_kb'),
                'extensions:'.implode(',', config('resume.allowed_extensions')),
                'mimetypes:'.implode(',', config('resume.allowed_mimes')),
            ],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();

        // Uploads are rate limited per user (privacy + shared-host protection).
        if (! RateLimiter::attempt(
            key: "resume-upload:{$user->id}",
            maxAttempts: config('resume.upload_rate_limit'),
            callback: fn () => true,
            decaySeconds: 3600,
        )) {
            throw ValidationException::withMessages([
                'file' => 'Too many uploads — please try again in an hour.',
            ]);
        }

        // Private disk, outside the public webroot.
        $path = $this->file->storeAs(
            "resumes/{$user->id}",
            Str::uuid().'.'.strtolower($this->file->getClientOriginalExtension()),
            config('resume.disk'),
        );

        $resume = $user->resumes()->create([
            'original_filename' => $this->file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $this->file->getMimeType(),
            'size_bytes' => $this->file->getSize(),
            'parse_status' => ParseStatus::Pending,
        ]);

        ParseResumeJob::dispatch($resume);

        $this->reset('file');
        session()->flash('resume-uploaded', 'Resume uploaded — parsing has started.');
        $this->awardAchievements();
    }

    public function delete(int $resumeId): void
    {
        $resume = Resume::query()->findOrFail($resumeId);

        Gate::authorize('delete', $resume);

        $resume->delete(); // model event hard-deletes the file
    }

    public function getResumesProperty(): Collection
    {
        return Auth::user()->resumes()->latest()->get();
    }

    public function getIsProcessingProperty(): bool
    {
        return $this->resumes->contains(
            fn (Resume $resume) => in_array($resume->parse_status, [ParseStatus::Pending, ParseStatus::Processing], true),
        );
    }

    public function render()
    {
        return view('livewire.resume-upload');
    }
}
