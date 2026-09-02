<x-app-layout title="Account settings">
    <x-slot name="header"><x-page-title title="Account settings" /></x-slot>

    <x-page width="narrow">
            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 card">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
    </x-page>
</x-app-layout>
