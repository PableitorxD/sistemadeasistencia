@props(['active' => false])

<a {{ $attributes->class([
    'rounded-lg px-3 py-1.5 font-medium',
    'bg-indigo-50 text-indigo-700' => $active,
    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
]) }}>{{ $slot }}</a>
