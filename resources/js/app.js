import './bootstrap';

// Livewire 3 ships and starts Alpine itself (loaded via @livewireScripts in
// the layouts). Importing Alpine here as well runs two instances, which
// breaks wire:model, URL-synced properties and x-data behaviour.
