@props(['label', 'value', 'tone' => 'slate'])

@php
    $color = [
        'emerald' => 'text-emerald-700',
        'red' => 'text-red-700',
        'indigo' => 'text-indigo-700',
        'slate' => 'text-slate-900',
    ][$tone] ?? 'text-slate-900';
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ $value }}</div>
</div>
