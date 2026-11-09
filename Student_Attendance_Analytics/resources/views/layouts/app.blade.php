@extends('layouts.base')

@php
    $user = auth()->user();
    $menu = config('navigation.'.$user->role, []);
    $initials = collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
    $otherLocale = app()->getLocale() === 'km' ? 'en' : 'km';
@endphp

@section('body')
<div class="drawer lg:drawer-open">
    <input id="sidebar-toggle" type="checkbox" class="drawer-toggle" />

    <div class="drawer-content flex min-h-screen flex-col">
        {{-- Top bar --}}
        <header id="topbar" class="sticky top-0 z-30">
            <div class="navbar bg-base-100 border-base-300 border-b px-4">
                <div class="navbar-start gap-2">
                    <label for="sidebar-toggle" class="btn btn-ghost btn-square drawer-button lg:hidden" aria-label="{{ __('Open menu') }}">
                        <i data-lucide="menu" class="size-5"></i>
                    </label>
                    <h1 class="text-lg font-semibold">@yield('title')</h1>
                </div>
                <div class="navbar-end gap-1">
                    <form method="POST" action="{{ route('locale', $otherLocale) }}">
                        @csrf
                        <button class="btn btn-ghost btn-sm">
                            <i data-lucide="languages" class="size-4"></i>{{ $otherLocale === 'km' ? 'ខ្មែរ' : 'English' }}
                        </button>
                    </form>
                    <button class="btn btn-ghost btn-sm btn-square" data-action="theme" aria-label="{{ __('Switch theme') }}"></button>
                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-ghost gap-2 px-2">
                            <div class="avatar avatar-placeholder">
                                <div class="w-8 rounded-full"><span class="text-xs">{{ $initials }}</span></div>
                            </div>
                            <span class="hidden text-left text-sm leading-tight sm:inline">
                                {{ app()->getLocale() === 'km' && $user->name_km ? $user->name_km : $user->name }}<br>
                                <span class="text-xs font-normal opacity-60">{{ __(ucfirst($user->role)) }}</span>
                            </span>
                        </summary>
                        <ul class="menu dropdown-content bg-base-100 rounded-box border-base-300 z-10 mt-2 w-48 border p-2 shadow-sm">
                            <li><a href="{{ route('profile') }}"><i data-lucide="circle-user" class="size-4"></i>{{ __('Profile') }}</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="flex w-full items-center gap-2"><i data-lucide="log-out" class="size-4"></i>{{ __('Log out') }}</button>
                                </form>
                            </li>
                        </ul>
                    </details>
                </div>
            </div>
        </header>

        <main class="flex-1 space-y-6 p-4 lg:p-6">
            @yield('content')
        </main>
    </div>

    {{-- Message after saving or deleting, e.g. "Student saved" --}}
    @if (session('status') || session('error'))
        <div class="toast toast-end toast-bottom z-50" data-toast>
            <div role="alert" class="alert {{ session('error') ? 'alert-error' : 'alert-success' }}">
                <i data-lucide="{{ session('error') ? 'circle-alert' : 'circle-check' }}" class="size-5"></i>
                <span>{{ session('error') ?? session('status') }}</span>
            </div>
        </div>
    @endif

    {{-- One delete confirmation for every page. Buttons with data-delete fill in the name and the address. --}}
    <dialog id="confirm-delete" class="modal">
        <div class="modal-box max-w-sm">
            <div class="bg-error/10 text-error mb-4 grid size-12 place-items-center rounded-2xl"><i data-lucide="trash-2" class="size-6"></i></div>
            <h3 class="text-lg font-bold">{{ __('Delete this record?') }}</h3>
            <p class="py-2 opacity-75"><span class="font-semibold" id="confirm-delete-name"></span> {{ __('will be removed permanently.') }}</p>
            <div class="modal-action">
                <form method="dialog"><button class="btn btn-ghost">{{ __('Cancel') }}</button></form>
                <form method="POST" id="confirm-delete-form">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-error">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    {{-- Sidebar: only the pages this role may open --}}
    <div class="drawer-side z-40" id="sidebar">
        <label for="sidebar-toggle" aria-label="{{ __('Close menu') }}" class="drawer-overlay"></label>
        <aside class="bg-base-100 border-base-300 flex min-h-full w-64 flex-col border-r">
            <a href="{{ route('home') }}" class="flex items-center gap-3 px-5 py-4">
                <span class="brand-mark text-primary-content grid size-10 place-items-center rounded-2xl">
                    <i data-lucide="clipboard-check" class="size-5"></i>
                </span>
                <span>
                    <span class="block leading-tight font-semibold">{{ __('Attendance') }}</span>
                    <span class="block text-xs opacity-60">{{ __('Demo High School') }}</span>
                </span>
            </a>
            <ul class="menu w-full grow gap-1 px-3">
                @foreach ($menu as $group => $items)
                    <li class="menu-title">{{ __($group) }}</li>
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ route($item['route']) }}" @class(['menu-active' => request()->routeIs($item['route'])])>
                                <i data-lucide="{{ $item['icon'] }}" class="size-4"></i>
                                <span>{{ __($item['label']) }}</span>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
            @if ($year = \App\Models\AcademicYear::current())
                <p class="px-5 py-4 text-xs opacity-50">{{ __('Academic year') }} {{ $year->name }}</p>
            @endif
        </aside>
    </div>
</div>
@endsection
