<div @if ($this->isProcessing) wire:poll.5s @endif>
    {{-- Upload form --}}
    <div class="card p-6">
        <h3 class="text-lg font-medium text-gray-900">Upload your resume</h3>

        <p class="mt-2 text-sm text-gray-600">
            PDF or DOCX, up to 5&nbsp;MB. We'll extract your skills, work history and
            qualifications — you review and correct everything before it's used for matching.
        </p>

        {{-- Privacy note (spec requirement) --}}
        <p class="mt-2 text-xs text-gray-500 border-l-4 border-gray-200 pl-3">
            <strong>Privacy:</strong> your resume is stored privately, is never shared with
            employers or third parties, and is only used to build your profile and match you
            to jobs. You can delete it — or your whole account — at any time, which
            permanently removes the file from our servers.
        </p>

        <form wire:submit="save" class="mt-4 space-y-4">
            <input
                type="file"
                wire:model="file"
                accept=".pdf,.docx"
                class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-brand-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-800"
            />

            @error('file')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div wire:loading wire:target="file" class="text-sm text-gray-500">Uploading…</div>

            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="file,save">
                Upload &amp; parse
            </x-primary-button>
        </form>

        @if (session('resume-uploaded'))
            <p class="mt-3 text-sm font-medium text-green-600">{{ session('resume-uploaded') }}</p>
        @endif
    </div>

    {{-- Resume list --}}
    <div class="mt-6 card divide-y divide-gray-100">
        @forelse ($this->resumes as $resume)
            <div class="flex items-center justify-between gap-4 p-4">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-900">{{ $resume->original_filename }}</p>
                    <p class="text-xs text-gray-500">
                        Uploaded {{ $resume->created_at->diffForHumans() }} ·
                        {{ number_format($resume->size_bytes / 1024, 0) }} KB
                    </p>

                    @if ($resume->parse_status === App\Enums\ParseStatus::Failed && $resume->parse_error)
                        <p class="mt-1 text-xs text-red-600">{{ Str::limit($resume->parse_error, 140) }}</p>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @switch($resume->parse_status)
                        @case(App\Enums\ParseStatus::Pending)
                        @case(App\Enums\ParseStatus::Processing)
                            <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                <svg class="mr-1 h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                Parsing…
                            </span>
                            @break

                        @case(App\Enums\ParseStatus::Parsed)
                            <span class="badge-success">Parsed</span>
                            <a href="{{ route('profile.review') }}" class="text-sm font-medium link">
                                Review profile →
                            </a>
                            @break

                        @case(App\Enums\ParseStatus::Failed)
                            <span class="badge-danger">Failed</span>
                            @break
                    @endswitch

                    <a
                        href="{{ URL::signedRoute('resumes.download', ['resume' => $resume]) }}"
                        class="text-sm text-gray-600 hover:text-gray-900"
                    >Download</a>

                    <button
                        type="button"
                        wire:click="delete({{ $resume->id }})"
                        wire:confirm="Delete this resume permanently? The file will be removed from our servers."
                        class="text-sm text-red-600 hover:text-red-500"
                    >Delete</button>
                </div>
            </div>
        @empty
            <p class="p-4 text-sm text-gray-500">No resumes uploaded yet.</p>
        @endforelse
    </div>
</div>
