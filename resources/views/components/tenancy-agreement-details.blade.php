@props(['agreement'])

@php($snapshot = $agreement->agreement_snapshot ?? [])

<div class="space-y-6">
    <section>
        <h3 class="text-base font-semibold text-slate-950">Parties</h3>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2 text-sm text-slate-700">
            <div><dt class="font-medium text-slate-500">Tenant</dt><dd class="mt-1">{{ $snapshot['tenant_name'] ?? $agreement->tenant?->name ?? 'Not recorded' }}</dd></div>
            <div><dt class="font-medium text-slate-500">Landlord</dt><dd class="mt-1">{{ $snapshot['landlord_name'] ?? $agreement->landlord?->name ?? 'Not recorded' }}</dd></div>
        </dl>
    </section>
    <section>
        <h3 class="text-base font-semibold text-slate-950">Property</h3>
        <dl class="mt-3 space-y-3 text-sm text-slate-700">
            <div><dt class="font-medium text-slate-500">Property title</dt><dd class="mt-1">{{ $snapshot['property_title'] ?? $agreement->property?->title ?? 'Not recorded' }}</dd></div>
            <div><dt class="font-medium text-slate-500">Property address</dt><dd class="mt-1">{{ ($snapshot['property_address'] ?? null) ?: 'Not recorded' }}</dd></div>
        </dl>
    </section>
    <section>
        <h3 class="text-base font-semibold text-slate-950">Rent and charges</h3>
        <dl class="mt-3 grid gap-3 sm:grid-cols-3 text-sm text-slate-700">
            <div><dt class="font-medium text-slate-500">Rent paid</dt><dd class="mt-1">{{ \App\Support\Currency::format($snapshot['rent_amount'] ?? null, $snapshot['currency'] ?? 'NGN') }}</dd></div>
            <div><dt class="font-medium text-slate-500">Caution fee</dt><dd class="mt-1">{{ filled($snapshot['caution_fee'] ?? null) ? \App\Support\Currency::format($snapshot['caution_fee'], $snapshot['currency'] ?? 'NGN') : 'Not recorded' }}</dd></div>
            <div><dt class="font-medium text-slate-500">Service charge</dt><dd class="mt-1">{{ filled($snapshot['service_charge'] ?? null) ? \App\Support\Currency::format($snapshot['service_charge'], $snapshot['currency'] ?? 'NGN') : 'Not recorded' }}</dd></div>
        </dl>
    </section>
    <section>
        <h3 class="text-base font-semibold text-slate-950">Tenancy period</h3>
        <dl class="mt-3 grid gap-3 sm:grid-cols-3 text-sm text-slate-700">
            <div><dt class="font-medium text-slate-500">Rental period</dt><dd class="mt-1">{{ $snapshot['rental_period'] ?? 'Not recorded' }}</dd></div>
            <div><dt class="font-medium text-slate-500">Start date</dt><dd class="mt-1">{{ filled($snapshot['tenancy_start_date'] ?? null) ? \Illuminate\Support\Carbon::parse($snapshot['tenancy_start_date'])->format('M j, Y') : 'Not recorded' }}</dd></div>
            <div><dt class="font-medium text-slate-500">Next due date</dt><dd class="mt-1">{{ filled($snapshot['tenancy_end_or_due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($snapshot['tenancy_end_or_due_date'])->format('M j, Y') : 'Not recorded' }}</dd></div>
        </dl>
    </section>
    <section>
        <h3 class="text-base font-semibold text-slate-950">Terms</h3>
        <div class="mt-3 space-y-3 text-sm leading-6 text-slate-700">
            <div><p class="font-medium text-slate-500">Property-specific terms</p><p class="mt-1 whitespace-pre-line">{{ ($snapshot['property_terms'] ?? null) ?: 'No additional property-specific terms were recorded.' }}</p></div>
            <div><p class="font-medium text-slate-500">General tenancy terms</p><ul class="mt-1 list-disc space-y-1 pl-5">@forelse (($snapshot['general_terms'] ?? []) as $term)<li>{{ $term }}</li>@empty<li>Use the property responsibly, report maintenance concerns promptly, and coordinate move-out through VerifyHomes.</li>@endforelse</ul></div>
        </div>
    </section>
</div>
