@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#213c5f">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <title>{{ $title ? "{$title} · " : '' }}{{ config('app.name', 'PathwayTT') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans text-gray-900 antialiased">
        <div class="min-h-full flex flex-col">
            <div class="bg-brand-800 text-white">
                <div class="max-w-md mx-auto px-6 pt-8 pb-12 text-center">
                    <a href="/" class="inline-flex text-white">
                        <x-application-logo class="text-2xl" />
                    </a>
                    <p class="mt-2 text-sm text-brand-100">
                        Find the jobs you're eligible for in Trinidad &amp; Tobago and abroad — and exactly what to learn for the ones you're not.
                    </p>
                </div>
            </div>

            <div class="flex-1 flex flex-col items-center px-4 pb-10">
                <div class="w-full sm:max-w-md -mt-6 card px-6 py-6">
                    {{ $slot }}
                </div>
                <p class="mt-6 text-xs text-gray-500">Times shown in AST (UTC-4) · v{{ config('app.version') }}</p>
            </div>
        </div>
    </body>
</html>
