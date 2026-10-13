<x-layouts.app title="Cambiar contraseña">
    <div class="mx-auto max-w-md">
        <h1 class="mb-1 text-2xl font-semibold text-slate-900">Cambiar contraseña</h1>
        <p class="mb-6 text-sm text-slate-500">
            Mínimo 8 caracteres, con al menos una letra y un número.
        </p>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium text-slate-700">Contraseña actual</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
                @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Nueva contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">Confirmar nueva contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('home') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Cancelar</a>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar contraseña
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
