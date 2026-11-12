<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (request()->routeIs('home'))
            @php
                $shareTitle = 'Cổng góp ý Đoàn TNTT GX Mỹ Vân';
                $shareDescription = 'Gửi góp ý về sinh hoạt của Xứ đoàn Giu-se Đặng Đình Viên và theo dõi phản hồi từ ban phụ trách.';
                $shareUrl = rtrim(config('app.url'), '/').'/';
                $shareImage = $shareUrl.'images/tntt-my-van.png';
            @endphp
            <meta name="description" content="{{ $shareDescription }}">
            <meta property="og:type" content="website">
            <meta property="og:locale" content="vi_VN">
            <meta property="og:site_name" content="Đoàn TNTT GX Mỹ Vân">
            <meta property="og:title" content="{{ $shareTitle }}">
            <meta property="og:description" content="{{ $shareDescription }}">
            <meta property="og:url" content="{{ $shareUrl }}">
            <meta property="og:image" content="{{ $shareImage }}">
            <meta property="og:image:type" content="image/png">
            <meta property="og:image:alt" content="Huy hiệu Đoàn TNTT GX Mỹ Vân">
            <meta name="twitter:card" content="summary">
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="{{ asset('images/tntt-my-van.png') }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ asset('images/tntt-my-van.png') }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>Đoàn TNTT GX Mỹ Vân</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
