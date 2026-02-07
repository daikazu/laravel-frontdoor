<x-mail::message>
# Welcome to {{ $appName }}!

@if($account)
Hello {{ $account->name }},
@else
Hello,
@endif

Thanks for signing up! Your account has been created and you're all set.

You can log in anytime by entering your email address — we'll send you a secure one-time code. No password needed.

If you didn't create this account, please contact us and we'll take care of it.

Thanks,<br>
{{ $appName }}
</x-mail::message>
