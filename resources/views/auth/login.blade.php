<x-layouts.guest title="Ingreso de estudiantes">
    <h2 class="text-lg font-semibold text-slate-900">Ingreso de estudiantes</h2>
    <p class="mb-5 text-sm text-slate-500">Usa tu correo electrónico o tu número de registro.</p>

    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf

        @error('login')
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <div>
            <label for="login" class="mb-1 block text-sm font-medium text-slate-700">Correo o número de registro</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300"> Recordarme
        </label>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Ingresar
        </button>
    </form>

    <p class="mt-5 text-center text-xs text-slate-500">
        ¿Eres administrador? <a href="{{ route('admin.login') }}" class="font-medium text-indigo-600 hover:underline">Ingresa aquí</a>
    </p>
</x-layouts.guest>
