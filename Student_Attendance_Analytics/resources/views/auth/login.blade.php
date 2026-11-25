@extends('layouts.auth')

@section('title', __('Log in'))

@section('card')
            <div class="card bg-base-100 border-base-300 w-full max-w-sm border">
                <form method="POST" action="{{ route('login.store') }}" class="card-body">
                    @csrf
                    <h2 class="text-2xl font-semibold">{{ __('Log in') }}</h2>
                    <p class="grow-0 text-sm opacity-60">{{ __('Enter your account to continue.') }}</p>

                    @if (session('status'))
                        <div role="alert" class="alert alert-success alert-soft mt-2 text-sm">
                            <i data-lucide="circle-check" class="size-4"></i><span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @error('login')
                        <div role="alert" class="alert alert-error alert-soft mt-2 text-sm">
                            <i data-lucide="circle-alert" class="size-4"></i><span>{{ $message }}</span>
                        </div>
                    @enderror

                    <fieldset class="fieldset mt-2">
                        <legend class="fieldset-legend">{{ __('Phone number or email') }}</legend>
                        <label class="input w-full @error('login') input-error @enderror">
                            <i data-lucide="smartphone" class="size-4 opacity-50"></i>
                            <input type="text" name="login" id="login" value="{{ old('login') }}" placeholder="{{ __('e.g. 012 345 678') }}" autocomplete="username" required autofocus />
                        </label>
                    </fieldset>

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Password') }}</legend>
                        <label class="input w-full">
                            <i data-lucide="lock" class="size-4 opacity-50"></i>
                            <input type="password" name="password" id="password" placeholder="{{ __('Enter your password') }}" autocomplete="current-password" required />
                            <button type="button" class="btn btn-ghost btn-xs btn-square -me-1" data-action="toggle-password" data-target="password" aria-label="{{ __('Show password') }}">
                                <i data-lucide="eye" class="size-4"></i>
                            </button>
                        </label>
                    </fieldset>

                    <div class="mt-1 flex items-center justify-between gap-2">
                        <label class="label text-sm">
                            <input type="checkbox" name="remember" id="remember" class="checkbox checkbox-sm" />
                            <span>{{ __('Remember me') }}</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="link link-primary link-hover text-sm">{{ __('Forgot password?') }}</a>
                    </div>

                    <button class="btn btn-primary mt-4 w-full">{{ __('Log in') }}</button>

                    <p class="mt-4 grow-0 text-center text-xs opacity-60">
                        {{ __('Admins see the whole school. Teachers see only their own classes.') }}
                    </p>
                </form>
            </div>
@endsection
