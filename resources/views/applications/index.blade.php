<x-app-layout title="My Applications">
    <x-slot name="header"><x-page-title title="My Applications" /></x-slot>

    <x-page>
            @livewire(App\Livewire\ApplicationTracker::class)
    </x-page>
</x-app-layout>
