@props([
    'inputId',
    'label' => 'Take photo',
    'kind' => 'photo',
    'facingMode' => 'user',
])

<div wire:ignore data-camera-capture data-camera-target="{{ $inputId }}" data-camera-kind="{{ $kind }}" data-camera-facing-mode="{{ $facingMode }}" class="space-y-3">
    <button type="button" data-camera-open class="admin-button admin-button-secondary">
        {{ $label }}
    </button>

    <div data-camera-modal hidden class="fixed inset-0 z-[70] overflow-y-auto p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="{{ $inputId }}-camera-title">
        <div data-camera-backdrop class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm"></div>

        <div class="relative z-10 mx-auto flex min-h-full max-w-2xl items-center">
            <div class="w-full overflow-hidden rounded-2xl border border-slate-700 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 id="{{ $inputId }}-camera-title" class="text-lg font-semibold text-slate-950">{{ $label }}</h3>
                        <p class="mt-1 text-sm text-slate-600">Position the subject in the frame before capturing.</p>
                    </div>
                    <button type="button" data-camera-cancel class="admin-button admin-button-quiet">Cancel</button>
                </div>

                <div class="space-y-4 p-5">
                    <div class="overflow-hidden rounded-xl bg-slate-950">
                        <video data-camera-video class="aspect-[4/3] w-full {{ $facingMode === 'user' ? '-scale-x-100' : '' }} object-contain" autoplay playsinline muted></video>
                        <img data-camera-image hidden alt="Captured {{ strtolower($kind) }} preview" class="aspect-[4/3] w-full object-contain" />
                        <canvas data-camera-canvas hidden></canvas>
                    </div>

                    <p data-camera-status aria-live="polite" class="text-sm text-slate-600">Camera access starts only after you choose {{ strtolower($label) }}.</p>

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <button type="button" data-camera-capture class="admin-button admin-button-primary">Capture photo</button>
                        <button type="button" data-camera-retake hidden class="admin-button admin-button-secondary">Retake</button>
                        <button type="button" data-camera-use hidden class="admin-button admin-button-primary">Use this photo</button>
                        <button type="button" data-camera-switch hidden class="admin-button admin-button-secondary">Switch camera</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
