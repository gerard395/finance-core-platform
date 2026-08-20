@props(['label', 'href' => null, 'active' => false, 'disabled' => false])
@if ($disabled)
    <span class="flex min-h-10 items-center rounded-lg px-3 py-2 text-sm text-slate-500" aria-disabled="true">{{ $label }}<span class="sr-only"> (nog niet beschikbaar)</span></span>
@else
    <a href="{{ $href }}" @class(['flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-blue-400', 'bg-blue-700 text-white' => $active, 'text-slate-200 hover:bg-slate-800' => ! $active]) @if($active) aria-current="page" @endif>{{ $label }}</a>
@endif
