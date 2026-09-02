<div class="space-y-8">
    <x-profile-strength :strength="$strength" compact />

    {{-- ── Profile details ─────────────────────────────────────────── --}}
    <section class="card p-6">
        <h3 class="card-title">Your details</h3>
        <p class="mt-1 text-sm text-gray-500">
            Extracted from your resume where possible — please check and correct.
            Fields you set are never changed by re-parsing.
        </p>

        <form wire:submit="saveProfile" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="full_name" value="Full name" />
                <x-text-input id="full_name" wire:model="full_name" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('full_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" wire:model="phone" class="mt-1 block w-full" />
            </div>

            <div>
                <x-input-label for="region" value="Region (T&T)" />
                <select id="region" wire:model="region" class="mt-1 form-control">
                    <option value="">— Select —</option>
                    @foreach (App\Livewire\ProfileReview::REGIONS as $region)
                        <option value="{{ $region }}">{{ $region }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="years_experience" value="Years of experience" />
                <x-text-input id="years_experience" type="number" min="0" max="60" wire:model="years_experience" class="mt-1 block w-full" />
            </div>

            <div>
                <x-input-label for="highest_education_level" value="Highest qualification" />
                <select id="highest_education_level" wire:model="highest_education_level" class="mt-1 form-control">
                    <option value="">— Select —</option>
                    @foreach ($qualificationTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="availability_date" value="Available from" />
                <x-text-input id="availability_date" type="date" wire:model="availability_date" class="mt-1 block w-full" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="summary" value="Professional summary" />
                <textarea id="summary" wire:model="summary" rows="3" class="mt-1 form-control"></textarea>
            </div>

            <fieldset class="sm:col-span-2">
                <legend class="text-sm font-medium text-gray-700">Local hiring readiness</legend>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="has_nis" class="rounded border-gray-300"> NIS number
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="has_bir" class="rounded border-gray-300"> BIR file number
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="has_drivers_permit" class="rounded border-gray-300"> Driver's permit
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="has_police_certificate" class="rounded border-gray-300"> Police certificate of character
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="willing_to_relocate" class="rounded border-gray-300"> Willing to relocate within T&amp;T
                    </label>
                </div>
            </fieldset>

            <div class="sm:col-span-2 flex items-center gap-3">
                <x-primary-button type="submit">Save details</x-primary-button>
                @if (session('saved-profile'))
                    <x-flash :message="session('saved-profile')" />
                @endif
            </div>
        </form>
    </section>

    {{-- ── Skills ──────────────────────────────────────────────────── --}}
    <section class="card p-6">
        <h3 class="card-title">Skills</h3>
        <p class="mt-1 text-sm text-gray-500">Confirm what we found and add anything missing. Rate yourself 1–5.</p>

        <div class="mt-4 flex flex-wrap gap-2">
            @forelse ($profile->skills as $skill)
                <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-800">
                    {{ $skill->name }}
                    <select
                        class="rounded border-0 bg-transparent py-0 pl-1 pr-6 text-xs text-gray-600"
                        wire:change="updateSkillProficiency({{ $skill->id }}, parseInt($event.target.value))"
                    >
                        <option value="">rate</option>
                        @for ($level = 1; $level <= 5; $level++)
                            <option value="{{ $level }}" @selected($skill->pivot->proficiency == $level)>{{ $level }}/5</option>
                        @endfor
                    </select>
                    @if ($skill->pivot->evidence_source === 'resume')
                        <span class="text-[10px] uppercase tracking-wide text-brand-500" title="Found in your resume">resume</span>
                    @endif
                    <button type="button" wire:click="removeSkill({{ $skill->id }})" class="text-gray-400 hover:text-red-500">&times;</button>
                </span>
            @empty
                <p class="text-sm text-gray-500">No skills yet — search below to add some.</p>
            @endforelse
        </div>

        <div class="relative mt-4 max-w-sm">
            <x-text-input
                wire:model.live.debounce.300ms="skillSearch"
                placeholder="Search skills (e.g. Excel, Welding, JavaScript)…"
                class="block w-full"
            />
            @if ($this->skillSuggestions->isNotEmpty())
                <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg">
                    @foreach ($this->skillSuggestions as $suggestion)
                        <li>
                            <button
                                type="button"
                                wire:click="addSkill({{ $suggestion->id }})"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                            >
                                {{ $suggestion->name }}
                                <span class="text-xs text-gray-400">{{ $suggestion->category }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- ── Work history ────────────────────────────────────────────── --}}
    <section class="card p-6">
        <div class="flex items-center gap-3">
            <h3 class="card-title">Work history</h3>
            @if (session('saved-work'))
                <x-flash :message="session('saved-work')" />
            @endif
        </div>

        <div class="mt-4 space-y-6">
            @foreach ($workRows as $id => $row)
                <div class="rounded-md border border-gray-200 p-4" wire:key="work-{{ $id }}">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Job title" />
                            <x-text-input wire:model="workRows.{{ $id }}.title" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('workRows.'.$id.'.title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Employer" />
                            <x-text-input wire:model="workRows.{{ $id }}.employer" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('workRows.'.$id.'.employer')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Started" />
                            <x-text-input type="date" wire:model="workRows.{{ $id }}.started_at" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label value="Ended" />
                            <x-text-input type="date" wire:model="workRows.{{ $id }}.ended_at" class="mt-1 block w-full" />
                            <label class="mt-1 flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" wire:model="workRows.{{ $id }}.is_current" class="rounded border-gray-300"> Current role
                            </label>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Description" />
                            <textarea wire:model="workRows.{{ $id }}.description" rows="2" class="mt-1 form-control"></textarea>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3">
                        <x-secondary-button wire:click="saveWorkHistory({{ $id }})">Save</x-secondary-button>
                        <button type="button" wire:click="deleteWorkHistory({{ $id }})" wire:confirm="Remove this work history entry?" class="text-sm text-red-600 hover:text-red-500">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium text-brand-600">+ Add a role</summary>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label value="Job title" />
                    <x-text-input wire:model="newWork.title" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('newWork.title')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Employer" />
                    <x-text-input wire:model="newWork.employer" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('newWork.employer')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Started" />
                    <x-text-input type="date" wire:model="newWork.started_at" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label value="Ended" />
                    <x-text-input type="date" wire:model="newWork.ended_at" class="mt-1 block w-full" />
                    <label class="mt-1 flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="newWork.is_current" class="rounded border-gray-300"> Current role
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label value="Description" />
                    <textarea wire:model="newWork.description" rows="2" class="mt-1 form-control"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <x-primary-button type="button" wire:click="addWorkHistory">Add role</x-primary-button>
                </div>
            </div>
        </details>
    </section>

    {{-- ── Education ───────────────────────────────────────────────── --}}
    <section class="card p-6">
        <div class="flex items-center gap-3">
            <h3 class="card-title">Education</h3>
            @if (session('saved-education'))
                <x-flash :message="session('saved-education')" />
            @endif
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($educationRows as $id => $row)
                <div class="rounded-md border border-gray-200 p-4" wire:key="education-{{ $id }}">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Institution" />
                            <x-text-input wire:model="educationRows.{{ $id }}.institution" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('educationRows.'.$id.'.institution')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Qualification" />
                            <select wire:model="educationRows.{{ $id }}.qualification_type" class="mt-1 form-control">
                                <option value="">— Select —</option>
                                @foreach ($qualificationTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Field of study" />
                            <x-text-input wire:model="educationRows.{{ $id }}.field" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label value="Completed" />
                            <x-text-input type="date" wire:model="educationRows.{{ $id }}.completed_at" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3">
                        <x-secondary-button wire:click="saveEducation({{ $id }})">Save</x-secondary-button>
                        <button type="button" wire:click="deleteEducation({{ $id }})" wire:confirm="Remove this education entry?" class="text-sm text-red-600 hover:text-red-500">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium text-brand-600">+ Add education</summary>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label value="Institution" />
                    <x-text-input wire:model="newEducation.institution" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('newEducation.institution')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Qualification" />
                    <select wire:model="newEducation.qualification_type" class="mt-1 form-control">
                        <option value="">— Select —</option>
                        @foreach ($qualificationTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('newEducation.qualification_type')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Field of study" />
                    <x-text-input wire:model="newEducation.field" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label value="Completed" />
                    <x-text-input type="date" wire:model="newEducation.completed_at" class="mt-1 block w-full" />
                </div>
                <div class="sm:col-span-2">
                    <x-primary-button type="button" wire:click="addEducation">Add education</x-primary-button>
                </div>
            </div>
        </details>
    </section>

    {{-- ── Certifications ──────────────────────────────────────────── --}}
    <section class="card p-6">
        <div class="flex items-center gap-3">
            <h3 class="card-title">Certifications</h3>
            @if (session('saved-certification'))
                <x-flash :message="session('saved-certification')" />
            @endif
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($certificationRows as $id => $row)
                <div class="rounded-md border border-gray-200 p-4" wire:key="certification-{{ $id }}">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Certification" />
                            <x-text-input wire:model="certificationRows.{{ $id }}.name" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('certificationRows.'.$id.'.name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Issuer" />
                            <x-text-input wire:model="certificationRows.{{ $id }}.issuer" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label value="Issued" />
                            <x-text-input type="date" wire:model="certificationRows.{{ $id }}.issued_at" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3">
                        <x-secondary-button wire:click="saveCertification({{ $id }})">Save</x-secondary-button>
                        <button type="button" wire:click="deleteCertification({{ $id }})" wire:confirm="Remove this certification?" class="text-sm text-red-600 hover:text-red-500">Remove</button>
                    </div>
                </div>
            @endforeach
        </div>

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium text-brand-600">+ Add certification</summary>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <x-input-label value="Certification" />
                    <x-text-input wire:model="newCertification.name" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('newCertification.name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Issuer" />
                    <x-text-input wire:model="newCertification.issuer" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label value="Issued" />
                    <x-text-input type="date" wire:model="newCertification.issued_at" class="mt-1 block w-full" />
                </div>
                <div class="sm:col-span-3">
                    <x-primary-button type="button" wire:click="addCertification">Add certification</x-primary-button>
                </div>
            </div>
        </details>
    </section>
</div>
