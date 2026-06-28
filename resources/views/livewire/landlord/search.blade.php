<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="space-y-3">
                <div>
                    <p class="admin-eyebrow">Quick search</p>
                    <h2 class="admin-panel-title">Find your listings and payments</h2>
                    <p class="admin-panel-copy">Search by property title, tenant name, or payment reference.</p>
                </div>

                <form method="GET" action="{{ route('landlord.search') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input name="q" type="search" value="{{ $query }}" placeholder="Search your landlord workspace" class="admin-control w-full sm:max-w-xl" />
                    <button type="submit" class="admin-button admin-button-primary">Search</button>
                </form>
            </div>
        </x-admin.panel>

        @if ($query === '')
            <x-admin.empty-state
                title="Start with a listing or tenant name."
                copy="Enter a property title, tenant name, or payment reference to jump to the right record."
            />
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Properties</p>
                            <h3 class="admin-panel-title">Matching listings</h3>
                        </div>
                        @if ($results['properties']->isEmpty())
                            <x-admin.empty-state
                                title="No properties found."
                                copy="Try another title or check your inspection queue."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['properties'] as $property)
                                    <a href="{{ route('landlord.properties.edit', $property) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                        <p class="text-sm font-semibold text-slate-900">{{ $property->title }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $property->city }} - {{ $property->listingIntentLabel() }}</p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Tenants</p>
                            <h3 class="admin-panel-title">Matching tenant names</h3>
                        </div>
                        @if ($results['tenants']->isEmpty())
                            <x-admin.empty-state
                                title="No tenants found."
                                copy="Try another name or search by payment reference."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['tenants'] as $tenant)
                                    <a href="{{ route('landlord.occupancy.index', ['tenant' => $tenant->id]) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                        <p class="text-sm font-semibold text-slate-900">{{ $tenant->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $tenant->email }}</p>
                                        <p class="mt-2 text-xs font-medium text-emerald-700">View occupancy</p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-admin.panel>

                <x-admin.panel class="lg:col-span-2">
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Payments</p>
                            <h3 class="admin-panel-title">Matching payment references</h3>
                        </div>
                        @if ($results['payments']->isEmpty())
                            <x-admin.empty-state
                                title="No payments found."
                                copy="Try another reference or search by property title."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['payments'] as $payment)
                                    <a href="{{ route('landlord.payments.index', ['reference' => $payment->reference]) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                        <p class="text-sm font-semibold text-slate-900">{{ $payment->reference }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ str($payment->transaction_type)->headline() }} - {{ str($payment->status)->headline() }}</p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-admin.panel>
            </div>
        @endif
    </div>
</div>
