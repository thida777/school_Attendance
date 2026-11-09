@extends('layouts.app')

@section('title', __($title))

@section('content')
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body items-center py-16 text-center">
            <span class="bg-primary/10 text-primary grid size-14 place-items-center rounded-2xl"><i data-lucide="hammer" class="size-7"></i></span>
            <h2 class="mt-2 text-xl font-semibold">{{ __($title) }}</h2>
            <p class="max-w-md text-sm opacity-70">
                {{ __('This page is designed and will be connected to the database in :when of the project plan.', ['when' => __($when)]) }}
            </p>
        </div>
    </div>
@endsection
