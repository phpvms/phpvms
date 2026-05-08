@component('mail::message')
  A new user has signed up!

  Name: {{ $user->name }}!
  Email: {{ $user->email }}
  State: {{ $user->state->getLabel() }}

  {{ config('app.name') }}
@endcomponent
