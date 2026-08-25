<section class="mt-6 rounded-xl bg-white p-6 shadow-sm" aria-labelledby="delivery-heading">
    <h2 id="delivery-heading" class="text-lg font-bold">Document en verzending</h2>
    <div class="mt-4 flex flex-wrap gap-3">
        <a href="{{ $downloadRoute }}" class="rounded-lg border border-blue-700 px-4 py-3 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">PDF downloaden</a>
        @if($canSend)
            @php($hasHistory=$deliveryHistory->requests !== [])
            @php($actionReadiness=$hasHistory ? $resendDelivery : $initialDelivery)
            @if($actionReadiness->ready() || $actionReadiness->status === \App\Application\Sales\SalesDocumentDeliveryReadinessStatus::MissingRecipient)
                <form method="POST" action="{{ $hasHistory ? $resendRoute : $sendRoute }}">
                    @csrf
                    <input type="hidden" name="delivery_request_id" value="{{ $deliveryRequestId }}">
                    <details class="mb-3 w-full rounded-lg border p-3">
                        <summary class="cursor-pointer font-medium">Eenmalig ander e-mailadres gebruiken</summary>
                        <label class="mt-3 block"><input type="checkbox" name="use_recipient_override" value="1"> Override activeren</label>
                        <label class="mt-3 block">E-mailadres<input type="email" name="recipient_email" class="mt-1 w-full rounded-lg border-slate-300" autocomplete="email"></label>
                        <label class="mt-3 block">Naam (optioneel)<input name="recipient_name" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300"></label>
                    </details>
                    @if(!$actionReadiness->ready())<p class="mb-3 text-sm text-amber-900">{{ $deliveryPresenter::readiness($actionReadiness->status) }} Gebruik alleen voor deze verzending desgewenst de override.</p>@endif
                    <button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white focus:ring-2 focus:ring-blue-700">{{ $hasHistory ? 'Opnieuw verzenden' : $sendLabel }}</button>
                </form>
            @else
                <p class="rounded-lg bg-amber-50 px-4 py-3 text-amber-900" role="status">{{ $deliveryPresenter::readiness($actionReadiness->status) }}</p>
            @endif
        @endif
    </div>

    <h3 class="mt-6 font-bold">Verzendhistorie</h3>
    @if($deliveryHistory->requests === [])
        <p class="mt-2 text-slate-600">Nog geen verzendingen.</p>
    @else
        <div class="mt-3 space-y-4">
            @foreach($deliveryHistory->requests as $deliveryRequest)
                <article class="rounded-lg border p-4">
                    <div class="flex flex-wrap justify-between gap-2"><strong>{{ $deliveryPresenter::status($deliveryRequest['status']) }}</strong><time datetime="{{ $deliveryRequest['requested_at'] }}">{{ \Carbon\CarbonImmutable::parse($deliveryRequest['requested_at'])->format('d-m-Y H:i') }}</time></div>
                    <p class="mt-2 text-sm">Ontvanger: {{ $deliveryRequest['recipient_name'] }} &lt;{{ $deliveryRequest['recipient_email'] }}&gt;</p>
                    <p class="text-sm">Artifact: {{ $deliveryRequest['artifact_id'] }}</p>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach($deliveryHistory->attempts as $attempt)
                            @if($attempt['delivery_request_id'] === $deliveryRequest['id'])
                                <li>Poging {{ $attempt['attempt_number'] }}: {{ $deliveryPresenter::status($attempt['result']) }}
                                    @if(isset($deliveryHistory->resolutions[$attempt['id']]))
                                        — {{ $deliveryPresenter::status($deliveryHistory->resolutions[$attempt['id']]['resolution_type']) }}
                                    @endif
                                </li>
                                @if($attempt['result'] === 'outcome_unknown' && !isset($deliveryHistory->resolutions[$attempt['id']]) && $canResolveDelivery)
                                    <li class="mt-2 flex flex-wrap gap-2">
                                        @foreach(['handled_externally'=>'Handmatig afgehandeld','authorize_resend'=>'Opnieuw verzenden toestaan'] as $resolutionType=>$resolutionLabel)
                                            <form method="POST" action="{{ route('sales.delivery.outcomes.resolve') }}">@csrf
                                                <input type="hidden" name="resolution_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                                <input type="hidden" name="delivery_request_id" value="{{ $deliveryRequest['id'] }}">
                                                <input type="hidden" name="delivery_attempt_id" value="{{ $attempt['id'] }}">
                                                <input type="hidden" name="resolution_type" value="{{ $resolutionType }}">
                                                <button class="rounded-lg border px-3 py-2 font-semibold focus:ring-2 focus:ring-blue-700">{{ $resolutionLabel }}</button>
                                            </form>
                                        @endforeach
                                    </li>
                                @endif
                            @endif
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    @endif
</section>
