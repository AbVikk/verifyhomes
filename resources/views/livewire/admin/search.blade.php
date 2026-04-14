<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="space-y-3">
                <div>
                    <p class="admin-eyebrow">Quick search</p>
                    <h2 class="admin-panel-title">Find properties, people, and payments</h2>
                    <p class="admin-panel-copy">Search by property title, tenant name, landlord name, or payment reference.</p>
                </div>

                <form method="GET" action="{{ route('admin.search') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input name="q" type="search" value="{{ $query }}" placeholder="Search the admin workspace" class="admin-control w-full sm:max-w-xl" />
                    <button type="submit" class="admin-button admin-button-primary">Search</button>
                </form>
            </div>
        </x-admin.panel>

        @if ($query === '')
            <x-admin.empty-state
                title="Start with a name or reference."
                copy="Enter a property title, tenant, landlord, or payment reference to jump to the right record."
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
                                copy="Try another title or switch to landlord or payment search."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['properties'] as $property)
                                    <a href="{{ route('admin.properties.show', $property) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
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
                            <h3 class="admin-panel-title">Matching tenant profiles</h3>
                        </div>
                        @if ($results['tenants']->isEmpty())
                            <x-admin.empty-state
                                title="No tenants found."
                                copy="Try a different name or search by payment reference."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['tenants'] as $tenant)
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                        <p class="text-sm font-semibold text-slate-900">{{ $tenant->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $tenant->email }}</p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Landlords</p>
                            <h3 class="admin-panel-title">Matching landlord profiles</h3>
                        </div>
                        @if ($results['landlords']->isEmpty())
                            <x-admin.empty-state
                                title="No landlords found."
                                copy="Try a different name or search by property title."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['landlords'] as $landlord)
                                    <a href="{{ route('admin.landlords.show', $landlord->landlordProfile) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                        <p class="text-sm font-semibold text-slate-900">{{ $landlord->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $landlord->email }}</p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Payments</p>
                            <h3 class="admin-panel-title">Matching payment references</h3>
                        </div>
                        @if ($results['payments']->isEmpty())
                            <x-admin.empty-state
                                title="No payments found."
                                copy="Try another reference or search by name."
                            />
                        @else
                            <div class="space-y-3">
                                @foreach ($results['payments'] as $payment)
                                    <a href="{{ route('admin.payments.index', ['reference' => $payment->reference]) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
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
