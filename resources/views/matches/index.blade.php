<x-app-layout title="My Matches">
    <x-slot name="header"><x-page-title title="My Matches" /></x-slot>

    <x-page>
            @livewire(App\Livewire\MatchList::class)
    </x-page>
</x-app-layout>
