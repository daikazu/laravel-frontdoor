<x-mail::message>
# Your Login Code

@if($account)
Hello {{ $account->name }},
@else
Hello,
@endif

Use the code below to complete your login to **{{ $appName }}**.

<x-mail::panel>
<div style="text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: bold;">
{{ $code }}
</div>
</x-mail::panel>

This code expires in **{{ $expiresInMinutes }} minutes**.

If you didn't request this code, you can safely ignore this email.

Thanks,<br>
{{ $appName }}
</x-mail::message>
