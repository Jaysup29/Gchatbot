<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'GlacierBot' }}</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    @livewireStyles
</head>
<body class="bg-gray-300 min-h-screen h-screen">
    <div class="w-full mx-auto h-full flex flex-col">
        {{-- All page content will be injected here --}}
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
