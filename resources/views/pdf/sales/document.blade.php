<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4; margin: 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; line-height: 1.45; }
        h1 { color: #102a43; font-size: 24pt; margin: 0 0 5mm; }
        h2 { border-bottom: 1px solid #d7dee7; font-size: 11pt; margin: 7mm 0 2mm; padding-bottom: 1mm; }
        .top { display: table; table-layout: fixed; width: 100%; }
        .top > div { display: table-cell; vertical-align: top; width: 50%; }
        .meta { border-collapse: collapse; margin-left: auto; width: 90%; }
        .meta th { color: #52606d; font-weight: normal; text-align: left; }
        table.lines { border-collapse: collapse; margin-top: 7mm; page-break-inside: auto; width: 100%; }
        .lines thead { display: table-header-group; }
        .lines tr { page-break-inside: avoid; }
        .lines th { background: #eaf0f6; color: #243b53; font-size: 8pt; padding: 2mm; text-align: left; }
        .lines td { border-bottom: 1px solid #e5eaf0; padding: 2mm; vertical-align: top; }
        .num { text-align: right !important; white-space: nowrap; }
        .totals { border-collapse: collapse; margin: 5mm 0 0 auto; min-width: 70mm; }
        .totals td { padding: 1.2mm 2mm; }
        .totals tr:last-child { border-top: 2px solid #243b53; font-size: 11pt; font-weight: bold; }
        .tax { color: #52606d; font-size: 8.5pt; }
        .notice { background: #f4f7fa; border-left: 3px solid #627d98; margin-top: 5mm; padding: 3mm; }
        .footer { color: #7b8794; font-size: 8pt; margin-top: 9mm; }
    </style>
</head>
<body>
@php($c = $model->content)
<div class="top">
    <div>
        <h1>{{ $title }}</h1>
        <strong>{{ $c['issuer']['name'] }}</strong><br>
        {{ $c['issuer']['line_1'] }}<br>
        @if($c['issuer']['line_2']){{ $c['issuer']['line_2'] }}<br>@endif
        {{ $c['issuer']['postal_code'] }} {{ $c['issuer']['city'] }} · {{ $c['issuer']['country'] }}<br>
        KvK: {{ $c['issuer']['registration_number'] }}
    </div>
    <div>
        <table class="meta">
            <tr><th>Nummer</th><td>{{ $c['document']['number'] }}</td></tr>
            <tr><th>Datum</th><td>{{ $c['document']['date'] }}</td></tr>
            @if($c['document']['valid_until'] ?? null)<tr><th>Geldig tot</th><td>{{ $c['document']['valid_until'] }}</td></tr>@endif
            @if($c['document']['supply_date'] ?? null)<tr><th>Prestatiedatum</th><td>{{ $c['document']['supply_date'] }}</td></tr>@endif
            @if($c['document']['due_date'] ?? null)<tr><th>Vervaldatum</th><td>{{ $c['document']['due_date'] }}</td></tr>@endif
            @if($c['document']['source_invoice_number'] ?? null)<tr><th>Betreft factuur</th><td>{{ $c['document']['source_invoice_number'] }}</td></tr>@endif
        </table>
    </div>
</div>
<h2>Klant</h2>
<strong>{{ $c['customer']['name'] }}</strong> ({{ $c['customer']['number'] }})<br>
{{ $c['customer']['address']['line_1'] }}<br>
@if($c['customer']['address']['line_2']){{ $c['customer']['address']['line_2'] }}<br>@endif
{{ $c['customer']['address']['postal_code'] }} {{ $c['customer']['address']['city'] }} · {{ $c['customer']['address']['country'] }}
@if($c['customer']['vat_id'] ?? null)<br>Btw-id: {{ $c['customer']['vat_id'] }}@endif
<table class="lines">
    <thead><tr><th>Omschrijving</th><th class="num">Aantal</th><th class="num">Prijs</th><th class="num">Netto</th>@if(isset($c['tax_summary']))<th>Btw-behandeling</th><th class="num">Btw</th><th class="num">Bruto</th>@endif</tr></thead>
    <tbody>
    @foreach($c['lines'] as $line)
        <tr><td>{{ $line['description'] }}</td><td class="num">{{ $line['quantity'] }}</td><td class="num">{{ $c['document']['currency'] }} {{ $line['unit_price'] }}</td><td class="num">{{ $c['document']['currency'] }} {{ $line['net'] }}</td>@if(isset($c['tax_summary']))<td>{{ match($line['treatment']) { 'domestic_standard' => $line['rate'].'% btw', 'zero_rated' => 'BTW 0%', 'reverse_charge_eu_service' => 'EU-dienst · btw verlegd', 'intra_community_goods' => 'Intracommunautaire levering', 'exempt' => 'Vrijgesteld', 'outside_scope' => 'Buiten Nederlandse btw-heffing', default => $line['treatment'] } }}</td><td class="num">{{ $c['document']['currency'] }} {{ $line['tax'] }}</td><td class="num">{{ $c['document']['currency'] }} {{ $line['gross'] }}</td>@endif</tr>
    @endforeach
    </tbody>
</table>
@if(isset($c['tax_summary']))
<h2>Btw-overzicht</h2>
@foreach($c['tax_summary'] as $tax)
    <div class="tax">{{ match($tax['treatment']) { 'domestic_standard' => $tax['rate'].'% btw', 'zero_rated' => 'BTW 0%', 'reverse_charge_eu_service' => 'EU-dienst · btw verlegd', 'intra_community_goods' => 'Intracommunautaire levering', 'exempt' => 'Vrijgesteld', 'outside_scope' => 'Buiten Nederlandse btw-heffing', default => $tax['treatment'] } }} — netto {{ $c['document']['currency'] }} {{ $tax['net'] }}, btw {{ $c['document']['currency'] }} {{ $tax['tax'] }}</div>
@endforeach
@endif
<table class="totals"><tr><td>Netto</td><td class="num">{{ $c['document']['currency'] }} {{ $c['totals']['net'] }}</td></tr>@if(isset($c['totals']['tax']))<tr><td>Btw</td><td class="num">{{ $c['document']['currency'] }} {{ $c['totals']['tax'] }}</td></tr><tr><td>Totaal</td><td class="num">{{ $c['document']['currency'] }} {{ $c['totals']['gross'] }}</td></tr>@endif</table>
@if($c['supplier_fiscal']['vat_id'] ?? null)<div class="footer">Btw-id leverancier: {{ $c['supplier_fiscal']['vat_id'] }}</div>@endif
@if($c['issuer']['iban'] ?? null)<div class="notice">Betaal onder vermelding van <strong>{{ $c['payment_reference'] }}</strong> aan {{ $c['issuer']['account_holder'] }}, IBAN {{ $c['issuer']['iban'] }}@if($c['issuer']['bic']) · BIC {{ $c['issuer']['bic'] }}@endif.</div>@endif
</body>
</html>
