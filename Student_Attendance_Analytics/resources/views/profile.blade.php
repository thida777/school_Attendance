@extends('layouts.app')

@section('title', __('Profile'))

@php
    $km = app()->getLocale() === 'km';@endphp

@section('content')
    <div class="mx-auto grid w-full max-w-5xl grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Who is logged in --}}
        <div class="card bg-base-100 border-base-300 h-fit border">
            <div class="card-body items-center text-center">
                {{-- Profile photo: tap the camera, see the preview, then Save --}}
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="flex flex-col items-center gap-2" data-photo-form>
                    @csrf
                    <div class="relative">
                        <div class="avatar avatar-placeholder">
                            <div class="bg-neutral text-neutral-content ring-base-100 w-24 rounded-full shadow-md ring-4">
                                <img @if ($user->photoUrl()) src="{{ $user->photoUrl() }}" @endif alt="{{ $user->name }}" @class(['size-full object-cover', 'hidden' => ! $user->photoUrl()]) data-photo-preview />
                                <span @class(['text-3xl', 'hidden' => $user->photoUrl()]) data-photo-initials>{{ $user->initials() }}</span>
                            </div>
                        </div>
                        <label class="photo-camera btn btn-circle btn-sm absolute -inset-e-1 bottom-0" title="{{ $user->photoUrl() ? __('Change photo') : __('Add photo') }} · {{ __('JPG, PNG or WebP · up to 2 MB') }}">
                            <i data-lucide="camera" class="size-4"></i>
                            <span class="sr-only">{{ $user->photoUrl() ? __('Change photo') : __('Add photo') }}</span>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden" data-photo-input />
                        </label>
                    </div>

                    {{-- Shown only after a picture is chosen --}}
                    <div class="hidden gap-2" data-photo-actions>
                        <button class="btn btn-primary btn-sm"><i data-lucide="check" class="size-4"></i>{{ __('Save photo') }}</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-photo-cancel>{{ __('Cancel') }}</button>
                    </div>
                    <p class="text-error hidden text-xs" data-photo-error data-too-big="{{ __('The photo is too big. Choose one under 2 MB.') }}" data-wrong-type="{{ __('Choose a JPG, PNG or WebP picture.') }}"></p>
                    @error('photo')<p class="text-error text-xs">{{ $message }}</p>@enderror                </form>
                @if ($user->photoUrl())
                    <form method="POST" action="{{ route('profile.photo.destroy') }}" data-photo-remove>
                        @csrf
                        @method('DELETE')
                        <button class="link link-hover text-error text-xs">{{ __('Remove photo') }}</button>
                    </form>
                @endif
                <h2 class="mt-2 text-lg font-semibold">{{ $km ? $user->name_km : $user->name }}</h2>
                <p class="text-sm opacity-60">{{ $user->email ?? trim(chunk_split((string) $user->phone, 3, ' ')) }}</p>
                <span class="badge badge-soft badge-primary">{{ $user->isAdmin() ? __('Administrator') : __('Teacher') }}</span>
                <form method="POST" action="{{ route('logout') }}" class="mt-4 w-full">
                    @csrf
                    <button class="btn btn-block"><i data-lucide="log-out" class="size-4"></i>{{ __('Log out') }}</button>
                </form>
            </div>
        </div>

        <div class="space-y-4 lg:col-span-2">
            {{-- Personal details --}}
            <div class="card bg-base-100 border-base-300 border">
                <form method="POST" action="{{ route('profile.update') }}" class="card-body">
                    @csrf
                    @method('PUT')
                    <h3 class="card-title text-base">{{ __('Personal details') }}</h3>
                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                        @include('partials.field', ['name' => 'name', 'label' => __('Name (Latin)'), 'default' => $user->name])
                        @include('partials.field', ['name' => 'name_km', 'label' => __('Name (Khmer)'), 'default' => $user->name_km])
                        @include('partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'default' => $user->email, 'required' => $user->isAdmin()])
                        @include('partials.field', ['name' => 'phone', 'label' => __('Phone'), 'default' => $user->phone, 'hint' => __('Used to log in.')])
                    </div>
                    <div class="card-actions mt-2 justify-end"><button class="btn btn-primary">{{ __('Save changes') }}</button></div>
                </form>
            </div>

            {{-- Language and theme --}}
            <div class="card bg-base-100 border-base-300 border">
                <div class="card-body">
                    <h3 class="card-title text-base">{{ __('Language and appearance') }}</h3>
                    <div class="flex items-center justify-between gap-4 py-2">
                        <div>
                            <div class="font-medium">{{ __('Language') }}</div>
                            <div class="text-sm opacity-60">{{ __('English or Khmer for every screen.') }}</div>
                        </div>
                        <form method="POST" action="{{ route('locale', $km ? 'en' : 'km') }}">
                            @csrf
                            <button class="btn btn-sm"><i data-lucide="languages" class="size-4"></i>{{ $km ? 'English' : 'ខ្មែរ' }}</button>
                        </form>
                    </div>
                    <div class="border-base-300 flex items-center justify-between gap-4 border-t py-2">
                        <div>
                            <div class="font-medium">{{ __('Theme') }}</div>
                            <div class="text-sm opacity-60">{{ __('Light or dark colours.') }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-square" data-action="theme" aria-label="{{ __('Switch theme') }}"></button>
                    </div>
                </div>
            </div>

            {{-- Password --}}
            <div class="card bg-base-100 border-base-300 border">
                <form method="POST" action="{{ route('profile.password') }}" class="card-body">
                    @csrf
                    @method('PUT')
                    <h3 class="card-title text-base">{{ __('Change password') }}</h3>
                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-3">
                        @include('partials.field', ['name' => 'current_password', 'label' => __('Current password'), 'type' => 'password'])
                        @include('partials.field', ['name' => 'password', 'label' => __('New password'), 'type' => 'password', 'hint' => __('At least 8 characters')])
                        @include('partials.field', ['name' => 'password_confirmation', 'label' => __('Repeat new password'), 'type' => 'password'])
                    </div>
                    <p class="text-sm opacity-60">{{ __('After the change, the Android app asks you to log in again.') }}</p>
                    <div class="card-actions mt-2 justify-end"><button class="btn">{{ __('Update password') }}</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
