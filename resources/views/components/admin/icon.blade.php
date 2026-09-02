@props(['name', 'size' => 20])

<svg {{ $attributes->merge(['class' => 'ui-icon', 'width' => $size, 'height' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('dashboard')<path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>@break
        @case('search')<circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('users')<path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M17 11a3 3 0 0 0 0-6m3 15v-1.5a4.5 4.5 0 0 0-2.6-4.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('circles')<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3 3"/>@break
        @case('sos')<path d="M12 3 2.8 19h18.4L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4m0 3.1v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>@break
        @case('shield')<path d="M12 3 5 6v5c0 4.4 2.7 8.2 7 10 4.3-1.8 7-5.6 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>@break
        @case('support')<path d="M4 12a8 8 0 0 1 16 0v4a2 2 0 0 1-2 2h-2v-6h4M4 12h4v6H6a2 2 0 0 1-2-2v-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M16 18c0 1.7-1.8 3-4 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('billing')<rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18m4 6h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('megaphone')<path d="m4 13 10-4v8L4 13Zm10-4 4-3v14l-4-3" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m6 14 1.3 5h3.2l-1.6-6.2" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>@break
        @case('chart')<path d="M4 20V10m6 10V4m6 16v-7m4 7H2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('system')<path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="8" cy="6" r="1.5" fill="currentColor"/><circle cx="16" cy="12" r="1.5" fill="currentColor"/><circle cx="10" cy="18" r="1.5" fill="currentColor"/>@break
        @case('bell')<path d="M18 9a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('sun')<circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('moon')<path d="M20 15.2A8.5 8.5 0 0 1 8.8 4a8.5 8.5 0 1 0 11.2 11.2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>@break
        @case('menu')<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('chevron-down')<path d="m7 9 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>@break
        @case('arrow-right')<path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>@break
        @case('lock')<rect x="5" y="10" width="14" height="10" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8"/>@break
        @case('mail')<rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>@break
        @case('eye')<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/>@break
        @case('eye-off')<path d="m3 3 18 18M10.7 6.1c.4-.1.8-.1 1.3-.1 6 0 9.5 6 9.5 6a17 17 0 0 1-2.6 3.3M6.2 6.2A16.8 16.8 0 0 0 2.5 12s3.5 6 9.5 6c1.2 0 2.3-.2 3.3-.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9.8 9.8a3 3 0 0 0 4.4 4.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('keypad')<path d="M7 5h.01M12 5h.01M17 5h.01M7 10h.01M12 10h.01M17 10h.01M7 15h.01M12 15h.01M17 15h.01M12 20h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>@break
        @case('logout')<path d="M10 5H5v14h5m4-4 4-3-4-3m4 3H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>@break
        @case('refresh')<path d="M20 6v5h-5M4 18v-5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 9A7 7 0 0 0 6.1 6.1L4 9m16 6-2.1 2.9A7 7 0 0 1 5.5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @case('activity')<path d="M3 12h4l2-6 4 12 2-6h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>@break
        @case('check')<path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>@break
        @case('warning')<path d="M12 3 2.8 19h18.4L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4m0 3h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>@break
        @case('server')<rect x="3" y="4" width="18" height="6" rx="2" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="14" width="18" height="6" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>@break
        @default<circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/>@break
    @endswitch
</svg>
