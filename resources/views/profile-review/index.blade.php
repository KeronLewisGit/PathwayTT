<x-app-layout title="Review Your Profile">
    <x-slot name="header"><x-page-title title="Review Your Profile" /></x-slot>

    <x-page width="narrow">
            @livewire(App\Livewire\ProfileReview::class)
    </x-page>
</x-app-layout>
