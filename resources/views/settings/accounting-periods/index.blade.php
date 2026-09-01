<x-layouts.app :domain-user="$domainUser" :administration-context="$administrationContext" title="Perioden">
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex items-end justify-between gap-4">
            <div><p class="text-sm font-semibold text-blue-700">Beheer → Grootboek</p><h1 class="text-2xl font-bold">Perioden</h1><p class="mt-2 text-sm text-slate-600">Beheer boekjaren en de perioden die Accounting PostingDates toelaten of blokkeren.</p></div>
            @if($canManage)<a href="{{ route('settings.accounting-periods.create') }}" class="min-h-11 rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Boekjaar toevoegen</a>@endif
        </div>
        @switch($readiness->status)
            @case(\App\Application\Accounting\AccountingPeriodReadinessStatus::NoBookYear)
                <p class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900" role="status">Er is nog geen boekjaar ingericht. Nieuwe financiële boekingen kunnen niet worden geboekt totdat een volledige periodenindeling bestaat.</p>
                @break
            @case(\App\Application\Accounting\AccountingPeriodReadinessStatus::IncompleteCoverage)
                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900" role="status"><p>De periodenindeling is nog niet volledig. Nieuwe boekingen zonder passende periode worden geblokkeerd.</p>@if($readiness->uncoveredPostingDates !== [])<p class="mt-2">Niet gedekte historische boekingsdatums: {{ implode(', ', $readiness->uncoveredPostingDates) }}</p>@endif</div>
                @break
            @case(\App\Application\Accounting\AccountingPeriodReadinessStatus::Success)
                <p class="mb-5 rounded-lg bg-emerald-50 p-4 text-emerald-900" role="status">De periodenindeling is volledig en de historische boekingsdatums zijn gedekt.</p>
                @break
            @default
                <p class="mb-5 rounded-lg bg-red-50 p-4 text-red-900" role="alert">De periodenindeling bevat een integriteitsconflict. Neem contact op met beheer.</p>
        @endswitch
        <div class="overflow-x-auto rounded-xl border bg-white"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-3">Code</th><th class="p-3">Label</th><th class="p-3">Start</th><th class="p-3">Einde</th><th class="p-3">Perioden</th><th class="p-3">Dekking</th></tr></thead><tbody>
            @forelse($years as $year)<tr class="border-t"><td class="p-3 font-semibold"><a class="text-blue-700" href="{{ route('settings.accounting-periods.show', $year->id()->toString()) }}">{{ $year->code() }}</a></td><td class="p-3">{{ $year->label() }}</td><td class="p-3">{{ $year->startDate()->format('d-m-Y') }}</td><td class="p-3">{{ $year->endDate()->format('d-m-Y') }}</td><td class="p-3">{{ count($year->periods()) }}</td><td class="p-3">{{ $year->hasFullCoverage() ? 'Volledig' : 'Onvolledig' }}</td></tr>
            @empty<tr><td colspan="6" class="p-5 text-slate-600">Nog geen boekjaren ingericht.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</x-layouts.app>
