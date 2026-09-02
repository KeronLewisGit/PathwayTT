<x-app-layout title="My Resume">
    <x-slot name="header"><x-page-title title="My Resume" /></x-slot>

    <x-page>
            @livewire(App\Livewire\ResumeUpload::class)
    </x-page>
</x-app-layout>
