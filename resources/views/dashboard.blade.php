<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-lg font-medium">Welcome, {{ Auth::user()->name }}.</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Three steps: upload your resume, check the profile we extracted, tell us what you're looking for.
                        Then browse jobs you're actually eligible for.
                    </p>
                </div>
            </div>

            {{-- Phase 7 replaces these with live status cards (match count, gap plan progress). --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('resume.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-5 hover:shadow">
                    <p class="text-sm font-semibold text-gray-900">1. My Resume</p>
                    <p class="mt-1 text-xs text-gray-500">Upload a PDF or DOCX. We parse it privately.</p>
                </a>
                <a href="{{ route('profile.review') }}" class="block bg-white shadow-sm sm:rounded-lg p-5 hover:shadow">
                    <p class="text-sm font-semibold text-gray-900">2. My Profile</p>
                    <p class="mt-1 text-xs text-gray-500">Correct skills, experience and qualifications.</p>
                </a>
                <a href="{{ route('preferences.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-5 hover:shadow">
                    <p class="text-sm font-semibold text-gray-900">3. Preferences</p>
                    <p class="mt-1 text-xs text-gray-500">Industry, remote or local, salary floor.</p>
                </a>
                <a href="{{ route('matches.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-5 hover:shadow">
                    <p class="text-sm font-semibold text-gray-900">My Matches</p>
                    <p class="mt-1 text-xs text-gray-500">Ranked, scored, with what you're missing — or your Skills Gap Plan.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
