@props(['sales', 'listing' => null])

<div {{ $attributes->class('flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-3') }}>
    {{-- aset: foto sales --}}
    <div aria-hidden="true" class="size-12 shrink-0 rounded-full bg-stone-200"></div>
    <div class="min-w-0 flex-1">
        <p class="truncate font-medium">{{ $sales->name }}</p>
        @if ($whatsappUrl = $sales->whatsappUrl($listing))
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-full bg-brand px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-strong">WhatsApp {{ $sales->name }}</a>
                @if ($sales->contact_email)<a href="mailto:{{ $sales->contact_email }}" class="text-xs text-stone-600 underline underline-offset-2 hover:text-stone-950">Email {{ $sales->name }}</a>@endif
            </div>
        @else
            <p class="mt-0.5 text-sm text-stone-500">Kontak belum tersedia.</p>
        @endif
    </div>
</div>
