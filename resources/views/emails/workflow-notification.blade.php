<x-mail::message>
# {{ $heading }}

Hello,

{{ $messageText }}

@if ($actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name', 'VerifyHomes') }}
</x-mail::message>
