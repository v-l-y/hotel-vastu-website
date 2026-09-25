@php($iconName = $name ?? 'dot')
<span class="ui-icon-box" aria-hidden="true">
@switch($iconName)
@case('dashboard')
<svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v4h-6zM14 12h6v8h-6zM4 14h6v6H4z"/></svg>
@break
@case('front-desk')
<svg viewBox="0 0 24 24"><path d="M4 20h16M6 20v-9h12v9M8 11V7h8v4M9 15h6"/></svg>
@break
@case('restaurant')
<svg viewBox="0 0 24 24"><path d="M7 3v8M4 3v5c0 2 1 3 3 3s3-1 3-3V3M7 11v10M15 3v18M15 3c3 2 4 5 4 8h-4"/></svg>
@break
@case('restaurant-table')
<svg viewBox="0 0 24 24"><path d="M8 8h8v8H8zM10 5h4M10 19h4M5 10v4M19 10v4M9 3h6v2H9zM9 19h6v2H9zM3 9h2v6H3zM19 9h2v6h-2z"/></svg>
@break
@case('payments')
<svg viewBox="0 0 24 24"><path d="M4 6h16v12H4zM4 9h16M8 15h3"/></svg>
@break
@case('reports')
<svg viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
@break
@case('setup')
<svg viewBox="0 0 24 24"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="M4.9 4.9l2 2M17.1 17.1l2 2M19.1 4.9l-2 2M6.9 17.1l-2 2M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>
@break
@case('users')
<svg viewBox="0 0 24 24"><path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21v-2c0-3 2-5 6-5s6 2 6 5v2M17 11a3 3 0 1 0 0-6M16 14c4 0 6 2 6 5v2"/></svg>
@break
@case('security')
<svg viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6zM9 12l2 2 4-4"/></svg>
@break
@case('logout')
<svg viewBox="0 0 24 24"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></svg>
@break
@case('add')
<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
@break
@case('table')
<svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM4 10h16M9 5v14"/></svg>
@break
@case('calendar')
<svg viewBox="0 0 24 24"><path d="M5 5h14v15H5zM8 3v4M16 3v4M5 9h14"/></svg>
@break
@case('filter')
<svg viewBox="0 0 24 24"><path d="M4 5h16l-6 7v5l-4 2v-7z"/></svg>
@break
@case('search')
<svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="5.5"/><path d="M15 15l5 5"/></svg>
@break
@case('lock')
<svg viewBox="0 0 24 24"><path d="M6 10h12v10H6zM8 10V7a4 4 0 0 1 8 0v3"/></svg>
@break
@default
<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/></svg>
@endswitch
</span>
