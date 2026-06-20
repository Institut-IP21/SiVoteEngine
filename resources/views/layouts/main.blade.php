<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title')</title>

    {{-- Fonts are self-hosted (imported in app.js, bundled by Vite). No external CDN:
         a secret-ballot page must not leak voter IPs to third parties. --}}
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    @stack('scripts')
    @livewireStyles
</head>

<body>
    {{-- Two render paths share this layout:
         • Blade views via @extends fill the `body` section (e.g. the standalone ballot).
         • Full-page Livewire components (the live session page) render into `$slot`.
         `$slot` is undefined for @extends views, so the null-coalesce keeps them working. --}}
    @section('body')
    @show
    {{ $slot ?? '' }}
    @livewireScripts

</body>

</html>