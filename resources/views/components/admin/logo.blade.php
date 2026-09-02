@props(['compact' => false])

<div {{ $attributes->class(['orbit-brand', 'orbit-brand--compact' => $compact]) }} aria-label="Orbit Administration">
    <span class="orbit-brand__mark" aria-hidden="true">
        <svg viewBox="0 0 48 48" role="img">
            <circle cx="24" cy="24" r="7"></circle>
            <ellipse cx="24" cy="24" rx="19" ry="9" transform="rotate(-26 24 24)"></ellipse>
            <circle class="orbit-brand__satellite" cx="40" cy="17" r="3"></circle>
        </svg>
    </span>
    @unless($compact)
        <span class="orbit-brand__copy">
            <strong>Orbit</strong>
            <small>Administration</small>
        </span>
    @endunless
</div>
