@extends('layouts.auth')

@section('title', __('New password'))

@section('card')
            <div class="card bg-base-100 border-base-300 w-full max-w-sm border">
                <form method="POST" action="{{ route('password.update') }}" class="card-body">
                    @csrf
                    <span class="bg-primary/10 text-primary rounded-box grid size-12 place-items-center">
                        <i data-lucide="shield-check" class="size-6"></i>
                    </span>
                    <h2 class="mt-2 text-2xl font-semibold">{{ __('New password') }}</h2>

                    {{-- The same answer for every account, so nobody can find out which accounts exist --}}
                    <div role="alert" class="alert alert-info alert-soft mt-2 text-sm">
                        <i data-lucide="send" class="size-4"></i>
                        <span>{{ __('If this account is linked to Telegram, a 6-digit code was sent. It works for :minutes minutes.', ['minutes' => $minutes]) }}</span>
                    </div>

                    <input type="hidden" name="login" value="{{ old('login', $login) }}">
                    <p class="grow-0 text-sm"><span class="opacity-60">{{ __('Account') }}:</span> <span class="font-medium">{{ old('login', $login) }}</span></p>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Code from Telegram') }}</legend>
                        <label class="input w-full @error('code') input-error @enderror">
                            <i data-lucide="hash" class="size-4 opacity-50"></i>
                            <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="123456" autocomplete="one-time-code" required autofocus class="tracking-[0.4em]" />
                        </label>
                        @error('code')<p class="label text-error whitespace-normal">{{ $message }}</p>@enderror
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('New password') }}</legend>
                        <label class="input w-full @error('password') input-error @enderror">
                            <i data-lucide="lock" class="size-4 opacity-50"></i>
                            <input type="password" name="password" id="password" placeholder="••••••••" autocomplete="new-password" required />
                            <button type="button" class="btn btn-ghost btn-xs btn-square -me-1" data-action="toggle-password" data-target="password" aria-label="{{ __('Show password') }}">
                                <i data-lucide="eye" class="size-4"></i>
                            </button>
                        </label>
                        @error('password')<p class="label text-error whitespace-normal">{{ $message }}</p>@enderror
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Confirm new password') }}</legend>
                        <label class="input w-full">
                            <i data-lucide="lock" class="size-4 opacity-50"></i>
                            <input type="password" name="password_confirmation" placeholder="••••••••" autocomplete="new-password" required />
                        </label>
                    </fieldset>

                    <button class="btn btn-primary mt-4 w-full">{{ __('Save new password') }}</button>

                    <div class="mt-2 flex justify-between text-sm">
                        <a href="{{ route('password.request') }}" class="link link-hover">{{ __('Send a new code') }}</a>
                        <a href="{{ route('login') }}" class="link link-hover">{{ __('Back to log in') }}</a>
                    </div>
                </form>
            </div>
@endsection
