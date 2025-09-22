<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'GlacierBot' }}</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    @livewireStyles
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen h-screen transition-colors duration-200">
    <div class="w-full mx-auto h-full flex flex-col">
        {{-- All page content will be injected here --}}
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>