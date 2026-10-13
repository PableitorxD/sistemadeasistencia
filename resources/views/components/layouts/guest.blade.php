@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-100 px-4 text-slate-800 antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <span class="mx-auto mb-3 grid size-12 place-items-center rounded-xl bg-indigo-600 text-lg font-semibold text-white">SA</span>
            <h1 class="text-xl font-semibold text-slate-900">Sistema de Asistencia</h1>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
