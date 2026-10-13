@props(['status'])

@php
    $classes = match ($status) {
        \App\Models\Attendance::PRESENT => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        \App\Models\Attendance::ABSENT => 'bg-red-100 text-red-800 ring-red-200',
        \App\Models\Attendance::PENDING => 'bg-amber-100 text-amber-800 ring-amber-200',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    {{ \App\Models\Attendance::label($status) }}
</span>
