@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-slate-900">
                    <span class="grid size-8 place-items-center rounded-lg bg-indigo-600 text-sm text-white">SA</span>
                    Sistema de Asistencia
                </a>
                <nav class="flex gap-1 text-sm">
                    @if (auth()->user()->isAdmin())
                        <x-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">Asistencia</x-nav-link>
                        <x-nav-link :href="route('admin.activity.index')" :active="request()->routeIs('admin.activity.*')">Historial</x-nav-link>
                    @else
                        <x-nav-link :href="route('student.attendance.index')" :active="request()->routeIs('student.*')">Mi asistencia</x-nav-link>
                    @endif
                </nav>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <div class="text-right leading-tight">
                    <div class="font-medium text-slate-900">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-slate-500">
                        {{ auth()->user()->isAdmin() ? 'Administrador' : 'Estudiante · '.auth()->user()->registration_number }}
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'error' => 'border-red-200 bg-red-50 text-red-800', 'info' => 'border-sky-200 bg-sky-50 text-sky-800'] as $key => $classes)
            @if (session($key))
                <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}" role="alert">{{ session($key) }}</div>
            @endif
        @endforeach

        {{ $slot }}
    </main>
</body>
</html>
