{{-- Frame of the login, forgot password and reset password pages: brand panel + one card (@section('card')). --}}
@extends('layouts.base')

@section('body')
@php($otherLocale = app()->getLocale() === 'km' ? 'en' : 'km')
<div class="grid min-h-screen grid-cols-1 lg:grid-cols-2">

    {{-- Left: brand panel (hidden on phones) --}}
    <div class="hero-panel m-4 hidden flex-col justify-between p-12 lg:flex">
        <div class="flex items-center gap-3">
            <span class="bg-base-100 text-primary rounded-box grid size-10 place-items-center">
                <i data-lucide="clipboard-check" class="size-5"></i>
            </span>
            <span class="text-lg font-semibold">{{ __('Attendance') }}</span>
        </div>
        <div>
            <h1 class="text-4xl leading-tight font-semibold">{{ __('Student Attendance Analytics System') }}</h1>
            <p class="mt-4 max-w-md opacity-80">{{ __('Take attendance in one minute, and find the students who need help before it is too late.') }}</p>
        </div>
        <p class="text-sm opacity-70">{{ __('Demo High School') }}</p>
    </div>

    {{-- Right: login form --}}
    <div class="flex flex-col p-6">
        <div class="flex justify-end gap-1">
            <form method="POST" action="{{ route('locale', $otherLocale) }}">
                @csrf
                <button class="btn btn-ghost btn-sm"><i data-lucide="languages" class="size-4"></i>{{ $otherLocale === 'km' ? 'ខ្មែរ' : 'English' }}</button>
            </form>
            <button class="btn btn-ghost btn-sm btn-square" data-action="theme" aria-label="{{ __('Switch theme') }}"></button>
        </div>

        <div class="flex grow items-center justify-center">
            @yield('card')
        </div>
    </div>
</div>
@endsection
