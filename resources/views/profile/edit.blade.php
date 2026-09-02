<x-app-layout title="Account settings">
    <x-slot name="header"><x-page-title title="Account settings" description="Login details and account removal. Your resume-derived profile lives under My Profile." /></x-slot>

    <x-page>
        <div class="card p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </x-page>
</x-app-layout>
