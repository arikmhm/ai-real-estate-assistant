@props(['sales', 'listing' => null])

@php($whatsappUrl = $sales->whatsappUrl($listing))

<div {{ $attributes->class('flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-4') }}>
    <div class="flex items-center gap-3">
        @if ($sales->avatar_path)
            <img src="{{ asset($sales->avatar_path) }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-full bg-stone-200 object-cover">
        @else
            <div aria-hidden="true" class="size-14 shrink-0 rounded-full bg-stone-200"></div>
        @endif
        <div class="min-w-0">
            <p class="truncate font-medium">{{ $sales->name }}</p>
            <p class="text-sm text-stone-500">Sales properti</p>
        </div>
    </div>
    @if ($whatsappUrl)
        <ul class="space-y-1 border-t border-stone-100 pt-3 text-sm">
            <li>
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="-mx-2 flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-stone-50">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#25D366]/10 text-[#128C4B]">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="size-4"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                    </span>
                    <span class="sr-only">WhatsApp {{ $sales->name }}:</span>
                    <span class="truncate">{{ preg_replace('/^\+62(\d{3})(\d{3,4})(\d+)$/', '+62 $1-$2-$3', $sales->whatsapp_number) }}</span>
                </a>
            </li>
            @if ($sales->contact_email)
                <li>
                    <a href="mailto:{{ $sales->contact_email }}" class="-mx-2 flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-stone-50">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-stone-100 text-stone-700"><flux:icon.envelope variant="micro" class="size-4" /></span>
                        <span class="sr-only">Email {{ $sales->name }}:</span>
                        <span class="min-w-0 break-all">{{ $sales->contact_email }}</span>
                    </a>
                </li>
            @endif
        </ul>
    @else
        <p class="border-t border-stone-100 pt-3 text-sm text-stone-500">Kontak belum tersedia.</p>
    @endif
</div>
