@extends('layouts.base')

@section('title', __('Log in'))

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
            <div class="card bg-base-100 border-base-300 w-full max-w-sm border">
                <form method="POST" action="{{ route('login.store') }}" class="card-body">
                    @csrf
                    <h2 class="text-2xl font-semibold">{{ __('Log in') }}</h2>
                    <p class="grow-0 text-sm opacity-60">{{ __('Enter your account to continue.') }}</p>

                    @error('login')
                        <div role="alert" class="alert alert-error alert-soft mt-2 text-sm">
                            <i data-lucide="circle-alert" class="size-4"></i><span>{{ $message }}</span>
                        </div>
                    @enderror

                    <fieldset class="fieldset mt-2">
                        <legend class="fieldset-legend">{{ __('Email or phone') }}</legend>
                        <label class="input w-full @error('login') input-error @enderror">
                            <i data-lucide="mail" class="size-4 opacity-50"></i>
                            <input type="text" name="login" id="login" value="{{ old('login') }}" placeholder="admin@school.edu.kh" autocomplete="username" required autofocus />
                        </label>
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Password') }}</legend>
                        <label class="input w-full">
                            <i data-lucide="lock" class="size-4 opacity-50"></i>
                            <input type="password" name="password" id="password" placeholder="••••••••" autocomplete="current-password" required />
                            <button type="button" class="btn btn-ghost btn-xs btn-square -me-1" data-action="toggle-password" data-target="password" aria-label="{{ __('Show password') }}">
                                <i data-lucide="eye" class="size-4"></i>
                            </button>
                        </label>
                    </fieldset>

                    <label class="label mt-1 text-sm">
                        <input type="checkbox" name="remember" id="remember" class="checkbox checkbox-sm" />
                        <span>{{ __('Remember me') }}</span>
                    </label>

                    <button class="btn btn-primary mt-4 w-full">{{ __('Log in') }}</button>

                    <p class="mt-4 grow-0 text-center text-xs opacity-60">
                        {{ __('Admins see the whole school. Teachers see only their own classes.') }}
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
