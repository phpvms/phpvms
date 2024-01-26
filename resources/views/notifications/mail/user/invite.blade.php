@component('mail::message')
  # You have been invited to join {{ config('app.name') }}!

  You can use the link below to register an account with this email address.

  @component('mail::button', ['url' => $invite->link])
    Register now
  @endcomponent

  Thanks,<br>
  Management, {{ config('app.name') }}
@endcomponent
