@props([
    'for' => null,
])

@error($for)
    <p {{ $attributes->merge(['class' => 'admin-error']) }}>{{ $message }}</p>
@enderror
