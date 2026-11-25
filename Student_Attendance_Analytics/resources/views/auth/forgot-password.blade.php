@extends('layouts.auth')

@section('title', __('Forgot password'))

@section('card')
            <div class="card bg-base-100 border-base-300 w-full max-w-sm border">
                <form method="POST" action="{{ route('password.email') }}" class="card-body">
                    @csrf
                    <span class="bg-primary/10 text-primary rounded-box grid size-12 place-items-center">
                        <i data-lucide="key-round" class="size-6"></i>
                    </span>
                    <h2 class="mt-2 text-2xl font-semibold">{{ __('Forgot password') }}</h2>
                    <p class="grow-0 text-sm opacity-60">{{ __('Type your phone number or email. If your account is linked to Telegram, the school bot sends you a 6-digit code.') }}</p>

                    <fieldset class="fieldset mt-2">
                        <legend class="fieldset-legend">{{ __('Phone number or email') }}</legend>
                        <label class="input w-full @error('login') input-error @enderror">
                            <i data-lucide="smartphone" class="size-4 opacity-50"></i>
                            <input type="text" name="login" value="{{ old('login') }}" placeholder="{{ __('e.g. 012 345 678') }}" autocomplete="username" required autofocus />
                        </label>
                        @error('login')<p class="label text-error whitespace-normal">{{ $message }}</p>@enderror
                    </fieldset>

                    <button class="btn btn-primary mt-4 w-full"><i data-lucide="send" class="size-4"></i>{{ __('Send code by Telegram') }}</button>

                    <div role="alert" class="alert alert-soft mt-4 text-xs">
                        <i data-lucide="info" class="size-4"></i>
                        <span>{{ __('No Telegram? Ask the school admin to set a new password for you.') }}</span>
                    </div>

                    <a href="{{ route('login') }}" class="link link-hover mt-2 text-center text-sm">{{ __('Back to log in') }}</a>
                </form>
            </div>
@endsection
