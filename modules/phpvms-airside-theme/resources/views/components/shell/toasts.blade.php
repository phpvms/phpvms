@props(['shell'])

{{--
  TallStackUI's toast stack, fed two ways: the redirect's flash messages
  (plain session success/error, or the laracasts notifier) and the
  theme-agnostic `toast` browser event a Livewire component dispatches with
  `$this->dispatch('toast', kind: 'success'|'error', text: '...')`.

  The toast renders its title with x-html, so every message is HTML-escaped
  first: the flash on the server, the event text through the $escapeHtml magic.
--}}
@php($flash = $shell->flash())

<x-ts-toast />
<x-ts-dialog />

<div
    x-data
    x-on:toast.window="$tsui.interaction('toast')[$event.detail.kind === 'error' ? 'error' : 'success']($escapeHtml($event.detail.text)).send()"
    x-init="
        @if ($flash['success']) $tsui.interaction('toast').success(@js(e($flash['success']))).send(); @endif
        @if ($flash['error']) $tsui.interaction('toast').error(@js(e($flash['error']))).send(); @endif
    "
    data-flash-success="{{ $flash['success'] }}"
    data-flash-error="{{ $flash['error'] }}"
></div>
