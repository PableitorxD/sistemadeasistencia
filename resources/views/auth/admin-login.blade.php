<x-layouts.guest title="Ingreso de administrador">
    <h2 class="text-lg font-semibold text-slate-900">Administrador</h2>
    <p class="mb-5 text-sm text-slate-500">Ingresa con tus credenciales de administrador.</p>

    <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-4">
        @csrf

        @error('login')
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <div>
            <label for="username" class="mb-1 block text-sm font-medium text-slate-700">Usuario</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
            @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-800">
            Ingresar como administrador
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-slate-500">
        <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">← Ingreso de estudiantes</a>
    </p>
</x-layouts.guest>
