<div class="card p-6">
    <h3 class="card-title">What are you looking for?</h3>
    <p class="mt-1 text-sm text-gray-500">
        These preferences pre-filter the job list and drive your match scores.
        Leave anything blank to keep it open.
    </p>

    <form wire:submit="save" class="mt-6 space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="industry_id" value="Target industry" />
                <select id="industry_id" wire:model="industry_id" class="mt-1 form-control">
                    <option value="">— Any industry —</option>
                    @foreach ($this->industries as $industry)
                        <option value="{{ $industry->id }}">{{ $industry->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('industry_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="seniority" value="Desired seniority" />
                <select id="seniority" wire:model="seniority" class="mt-1 form-control">
                    <option value="">— Any —</option>
                    @foreach ($seniorities as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('seniority')" class="mt-1" />
            </div>
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">Work arrangement</legend>
            <p class="text-xs text-gray-500">
                "Remote (international)" means a foreign employer you work for from home in T&amp;T.
                Our AST timezone (UTC-4) lines up with US Eastern for half the year — a real advantage.
            </p>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($arrangements as $arrangement)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="work_arrangements" value="{{ $arrangement->value }}" class="form-check" />
                        {{ $arrangement->label() }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="Arr::flatten($errors->get('work_arrangements.*'))" class="mt-1" />
        </fieldset>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">Employment type</legend>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($employmentTypes as $type)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="employment_types" value="{{ $type->value }}" class="form-check" />
                        {{ $type->label() }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="Arr::flatten($errors->get('employment_types.*'))" class="mt-1" />
        </fieldset>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">Minimum salary</legend>
            <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="min_salary" value="Amount" />
                    <x-text-input id="min_salary" wire:model="min_salary" inputmode="decimal" placeholder="e.g. 8000" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('min_salary')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="min_salary_currency" value="Currency" />
                    <select id="min_salary_currency" wire:model="min_salary_currency" class="mt-1 form-control">
                        <option value="TTD">TTD</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="min_salary_period" value="Per" />
                    <select id="min_salary_period" wire:model="min_salary_period" class="mt-1 form-control">
                        <option value="hourly">Hour</option>
                        <option value="monthly">Month</option>
                        <option value="yearly">Year</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="willing_to_relocate" class="form-check" />
                Willing to relocate within T&amp;T (Trinidad or Tobago)
            </label>

            <div>
                <x-input-label for="availability_date" value="Available from" />
                <x-text-input id="availability_date" type="date" wire:model="availability_date" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('availability_date')" class="mt-1" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button type="submit">Save preferences</x-primary-button>

            @if (session('preferences-saved'))
                <x-flash :message="session('preferences-saved')" />
                <a href="{{ route('jobs.index') }}" class="text-sm font-medium link">See jobs →</a>
            @endif
        </div>
    </form>
</div>
