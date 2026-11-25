@extends('layouts.app')

@section('title', __('School settings'))

@section('content')
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card bg-base-100 border-base-300 border xl:col-span-2">
            <form method="POST" action="{{ route('settings.school.update') }}" class="card-body">
                @csrf
                @method('PUT')
                <h3 class="card-title text-base">{{ __('School name') }}</h3>
                <p class="text-sm opacity-60">{{ __('Type the name in both languages. Each page shows the one that matches its language.') }}</p>

                <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                    @include('partials.field', ['name' => 'school_name_en', 'label' => __('School name (English)'), 'default' => $nameEn, 'placeholder' => 'Demo High School'])
                    @include('partials.field', ['name' => 'school_name_km', 'label' => __('School name (Khmer)'), 'default' => $nameKm, 'placeholder' => 'វិទ្យាល័យសាកល្បង'])
                </div>

                <div class="card-actions mt-2 justify-end">
                    <button class="btn btn-primary"><i data-lucide="save" class="size-4"></i>{{ __('Save') }}</button>
                </div>
            </form>
        </div>

        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('Where the name appears') }}</h3>
                <ul class="space-y-3 text-sm">
                    <li class="flex gap-3"><i data-lucide="panel-left" class="text-primary size-5 shrink-0"></i><span>{{ __('The website: the menu and the login page') }}</span></li>
                    <li class="flex gap-3"><i data-lucide="smartphone" class="text-primary size-5 shrink-0"></i><span>{{ __('The Android app: the login and start screens') }}</span></li>
                    <li class="flex gap-3"><i data-lucide="file-text" class="text-primary size-5 shrink-0"></i><span>{{ __('Printed reports (PDF)') }}</span></li>
                    <li class="flex gap-3"><i data-lucide="send" class="text-primary size-5 shrink-0"></i><span>{{ __('Telegram messages to parents and password codes (Khmer name first, then English)') }}</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
