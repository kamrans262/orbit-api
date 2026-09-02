@props(['variant' => 'primary', 'type' => 'button', 'loadingText' => null])
<button type="{{ $type }}" {{ $attributes->class(['ui-button', 'ui-button--'.$variant]) }} @if($loadingText) data-loading-text="{{ $loadingText }}" @endif>
    <span class="ui-button__spinner" aria-hidden="true"></span>
    <span class="ui-button__content">{{ $slot }}</span>
</button>
