{{-- Chevron v yang berubah bentuk menjadi ^ saat <details class="accordion"> terbuka. --}}
<svg {{ $attributes->merge(['class' => 'accordion-chevron size-5 shrink-0 text-stone-400']) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
    <line class="accordion-chevron-left" x1="4" y1="8" x2="10" y2="14" />
    <line class="accordion-chevron-right" x1="10" y1="14" x2="16" y2="8" />
</svg>
