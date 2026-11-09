<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ __('Student Attendance') }}</title>

    {{-- Apply the saved theme before the page draws, so it never flashes white --}}
    <script>
        try { document.documentElement.dataset.theme = localStorage.getItem('theme') || 'light'; }
        catch { document.documentElement.dataset.theme = 'light'; }
    </script>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-base-200 min-h-screen">
    @yield('body')
    @stack('scripts')
</body>
</html>
