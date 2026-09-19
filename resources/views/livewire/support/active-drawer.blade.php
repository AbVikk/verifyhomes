<div>
@if ($requests->isNotEmpty())
    <div data-active-support-drawer>
        <button type="button" class="admin-topbar-profile-trigger shrink-0 whitespace-nowrap gap-2 px-3 text-sm font-semibold text-slate-100 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900" data-active-support-open aria-expanded="false" aria-controls="active-support-panel">
            <span class="hidden sm:inline">Active Support</span>
            <span class="sm:hidden">Support</span>
            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-teal-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $requests->count() }}</span>
        </button>

        <div class="fixed inset-0 z-[70] hidden" data-active-support-modal aria-hidden="true">
            <button type="button" class="absolute inset-0 h-full w-full bg-slate-950/45" data-active-support-close aria-label="Close active support"></button>
            <section id="active-support-panel" class="absolute inset-y-0 right-0 flex w-full max-w-[460px] flex-col border-l border-slate-200 bg-slate-50 shadow-2xl" role="dialog" aria-modal="true" aria-label="Active Support">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
                    <div class="min-w-0"><p class="admin-eyebrow">Active Support</p><h2 class="truncate text-lg font-semibold text-slate-950">{{ $selectedRequest?->subject }}</h2></div>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" data-active-support-close aria-label="Close active support">&#10005;</button>
                </header>

                @if ($requests->count() > 1)
                    <div class="border-b border-slate-200 bg-white px-5 py-3"><label class="sr-only" for="active-support-request">Choose support request</label><select id="active-support-request" wire:change="selectRequest($event.target.value)" class="admin-control admin-control-select w-full text-sm">@foreach ($requests as $request)<option value="{{ $request->id }}" @selected($selectedRequest?->id === $request->id)>{{ $request->reference }} - {{ $request->subject }}</option>@endforeach</select></div>
                @endif

                @if ($selectedRequest)
                    <div class="border-b border-slate-200 bg-white px-5 py-3 text-xs text-slate-600"><span class="font-mono">{{ $selectedRequest->reference }}</span> · <span class="font-medium">{{ str($selectedRequest->status)->replace('_', ' ')->headline() }}</span> · <a href="{{ route($routePrefix.'.support.show', $selectedRequest) }}" class="font-semibold text-teal-700 hover:underline">View full request</a></div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                        <div class="space-y-4">
                            @foreach ($selectedRequest->publicMessages as $message)
                                <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-sm font-semibold text-slate-900">{{ $message->sender_type === 'support' ? 'VerifyHomes Support' : ($message->user?->name ?? 'You') }}</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $message->body }}</p><p class="mt-2 text-xs text-slate-500">{{ $message->created_at->format('M j, Y g:i A') }}</p></article>
                            @endforeach
                        </div>
                        @if ($selectedRequest->publicAttachments->isNotEmpty())
                            <div class="mt-5"><p class="admin-eyebrow">Attachments</p><div class="mt-2 space-y-2">@foreach ($selectedRequest->publicAttachments as $attachment)<div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3"><p class="min-w-0 break-all text-sm font-medium text-slate-700">{{ $attachment->original_name }}</p><div class="flex shrink-0 gap-3 text-sm"><a href="{{ route($routePrefix.'.support.attachments.view', [$selectedRequest, $attachment]) }}" target="_blank" rel="noopener" class="admin-inline-link">View</a><a href="{{ route($routePrefix.'.support.attachments.download', [$selectedRequest, $attachment]) }}" class="admin-inline-link">Download</a></div></div>@endforeach</div></div>
                        @endif
                    </div>
                    <form wire:submit.prevent="sendReply" class="border-t border-slate-200 bg-white p-4"><label class="sr-only" for="active-support-reply">Reply to support</label><textarea id="active-support-reply" wire:model="replyBody" rows="3" class="admin-control w-full" placeholder="Reply to VerifyHomes Support"></textarea><x-input-error :messages="$errors->get('replyBody')" /><button type="submit" class="admin-button admin-button-primary mt-3 min-w-28" wire:loading.attr="disabled" wire:target="sendReply"><span wire:loading.remove wire:target="sendReply">Send reply</span><span wire:loading wire:target="sendReply">Sending...</span></button></form>
                @endif
            </section>
        </div>
    </div>
@endif
</div>
