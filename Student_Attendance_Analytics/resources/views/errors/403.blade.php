@extends('layouts.base')

@section('title', __('No permission'))

@section('body')
<div class="flex min-h-screen items-center justify-center p-6">
    <div class="card bg-base-100 border-base-300 w-full max-w-md border">
        <div class="card-body items-center text-center">
            <span class="bg-error/15 text-error grid size-14 place-items-center rounded-2xl"><i data-lucide="shield-x" class="size-7"></i></span>
            <h1 class="mt-2 text-xl font-semibold">{{ __('No permission') }}</h1>
            <p class="text-sm opacity-70">{{ __('You do not have permission to open this page.') }}</p>
            @auth
                <a href="{{ route('home') }}" class="btn btn-primary mt-4"><i data-lucide="house" class="size-4"></i>{{ __('Back to my start page') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary mt-4">{{ __('Log in') }}</a>
            @endauth
        </div>
    </div>
</div>
@endsection
