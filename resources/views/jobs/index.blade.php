<x-app-layout title="Jobs">
    <x-slot name="header"><x-page-title title="Jobs" /></x-slot>

    <x-page>
            @livewire(App\Livewire\JobList::class)
    </x-page>
</x-app-layout>
