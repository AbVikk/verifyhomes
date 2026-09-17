@php
    $priorityClasses = [
        'low' => 'bg-slate-100 text-slate-700',
        'normal' => 'bg-sky-50 text-sky-800',
        'high' => 'bg-amber-50 text-amber-800',
        'urgent' => 'bg-rose-50 text-rose-800',
    ];
@endphp

<div class="space-y-5">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" method="GET">
        <input name="search" value="{{ request('search') }}" placeholder="Search reference, subject, requester" class="rounded-lg border-slate-300 text-sm lg:col-span-2">
        <select name="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-lg border-slate-300 text-sm">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(request('category') === $category)>{{ (new \App\Models\SupportRequest(['category' => $category]))->categoryLabel() }}</option>
            @endforeach
        </select>
        <select name="assignment" class="rounded-lg border-slate-300 text-sm">
            <option value="">All assignments</option>
            @if($area === 'support-team')
                <option value="mine" @selected(request('assignment') === 'mine')>Assigned to me</option>
            @endif
            <option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned</option>
            @if($area === 'admin')
                <option value="escalated" @selected(request('assignment') === 'escalated')>Escalated</option>
                @foreach($supportStaff as $staff)
                    <option value="staff:{{ $staff->id }}" @selected(request('assignment') === 'staff:'.$staff->id)>{{ $staff->name }}</option>
                @endforeach
            @endif
        </select>
        <button class="w-fit rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Requester</th>
                    <th class="px-4 py-3">Priority</th>
                    <th class="px-4 py-3">Assignment</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Updated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($requests as $request)
                    <tr class="text-sm">
                        <td class="px-4 py-4">
                            <a class="font-medium text-teal-800 hover:underline" href="{{ $area === 'admin' ? route('admin.support.show', $request) : route('support-team.requests.show', $request) }}">{{ $request->reference }}</a>
                            <p class="mt-1 text-slate-600">{{ $request->subject }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $request->categoryLabel() }}</p>
                        </td>
                        <td class="px-4 py-4">{{ $request->user?->name }}<p class="text-xs text-slate-500">{{ str($request->role_snapshot)->headline() }}</p></td>
                        <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $priorityClasses[$request->priority] ?? $priorityClasses['normal'] }}">{{ str($request->priority)->headline() }}</span></td>
                        <td class="px-4 py-4">{{ $request->assignedTo?->name ?? 'Unassigned' }}@if($request->assigned_at)<p class="mt-1 text-xs text-slate-500">{{ $request->assigned_at->diffForHumans() }}</p>@endif</td>
                        <td class="px-4 py-4">{{ str($request->status)->replace('_', ' ')->headline() }}@if($request->escalated_at)<p class="mt-1 text-xs font-semibold text-amber-800">Escalated</p>@endif</td>
                        <td class="px-4 py-4">{{ $request->updated_at->diffForHumans() }}<p class="text-xs text-slate-500">{{ $request->created_at->format('M j, Y') }}</p></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No matching support requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $requests->links() }}
</div>
