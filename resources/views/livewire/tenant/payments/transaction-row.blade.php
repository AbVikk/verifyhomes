<tr class="align-top">
    <td class="px-4 py-4 text-sm text-slate-700">
        <p class="font-mono text-xs text-slate-800">{{ $transaction->reference }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $this->providerLabel($transaction->provider) }}</p>
    </td>
    <td class="px-4 py-4 text-sm text-slate-700">
        <p class="font-medium text-slate-900">{{ $this->transactionTypeSummary($transaction) }}</p>
        @if ($this->isInspectionBookingPayment($transaction))
            <p class="mt-1 text-slate-500">Inspection fee only - separate from rent or purchase.</p>
        @endif
    </td>
    <td class="px-4 py-4 text-sm text-slate-700">
        @if ($transaction->inspectionRequest)
            <p class="font-medium text-slate-900">{{ $transaction->inspectionRequest->property?->title ?? 'Inspection request' }}</p>
            <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $transaction->inspectionRequest->getKey()]) }}" class="admin-inline-link mt-2 inline-flex">{{ $this->relatedActionLabel($transaction) }}</a>
        @elseif ($transaction->property)
            <p class="font-medium text-slate-900">{{ $transaction->property->title }}</p>
            <a href="{{ route('properties.show', $transaction->property) }}" class="admin-inline-link mt-2 inline-flex">{{ $this->relatedActionLabel($transaction) }}</a>
        @else
            <p class="text-slate-600">No related record</p>
        @endif
    </td>
    <td class="px-4 py-4 text-sm text-slate-700"><p class="font-medium text-slate-900">{{ $this->formatMoney($transaction->gross_amount, $transaction->currency) }}</p></td>
    <td class="px-4 py-4 text-sm text-slate-700"><span class="admin-badge admin-badge-neutral">{{ str($transaction->status)->headline() }}</span><p class="mt-2 text-slate-500">{{ $this->statusSummary($transaction) }}</p></td>
    <td class="px-4 py-4 text-sm text-slate-500">{{ $transaction->created_at->diffForHumans() }}</td>
</tr>
