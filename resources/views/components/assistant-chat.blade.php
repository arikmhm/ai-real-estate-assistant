<div data-assistant-chat data-endpoint="{{ route('assistant.message') }}" data-catalog-url="{{ route('properties.index') }}">
    <button type="button" data-assistant-toggle aria-expanded="false" aria-controls="assistant-panel" aria-label="Buka asisten properti"
        class="group fixed bottom-5 right-5 z-40 flex size-14 items-center justify-center rounded-full bg-stone-950 text-white shadow-xl shadow-stone-900/20 transition hover:scale-105 hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-950">
        <flux:icon.chat-bubble-left-right variant="outline" class="size-6 group-aria-expanded:hidden" />
        <flux:icon.x-mark variant="outline" class="hidden size-6 group-aria-expanded:block" />
    </button>

    <section id="assistant-panel" data-assistant-panel role="dialog" aria-labelledby="assistant-title" hidden
        class="fixed inset-0 z-50 flex flex-col bg-white sm:inset-auto sm:bottom-24 sm:right-5 sm:h-[min(34rem,calc(100dvh-8rem))] sm:w-[min(24rem,calc(100vw-2.5rem))] sm:rounded-2xl sm:border sm:border-stone-200 sm:shadow-2xl sm:shadow-stone-900/15 sm:data-expanded:h-[calc(100dvh-8rem)] sm:data-expanded:w-[min(44rem,calc(100vw-2.5rem))]">
        <header class="flex items-center gap-3 border-b border-stone-200 px-4 py-3">
            <div aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-stone-950 text-white"><flux:icon.sparkles variant="mini" class="size-4" /></div>
            <div class="min-w-0 flex-1">
                <h2 id="assistant-title" class="truncate text-sm font-medium">Asisten properti</h2>
                <p class="flex items-center gap-1.5 text-xs text-stone-500"><span aria-hidden="true" class="size-1.5 rounded-full bg-green-500"></span>Demo · data sintetis</p>
            </div>
            <button type="button" data-assistant-clear aria-label="Hapus percakapan" title="Hapus percakapan" class="rounded-full p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-950"><flux:icon.trash variant="mini" class="size-4" /></button>
            <button type="button" data-assistant-expand aria-label="Perbesar jendela" title="Perbesar / perkecil" class="hidden rounded-full p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-950 sm:block">
                <flux:icon.arrows-pointing-out variant="mini" class="size-4 in-data-expanded:hidden" />
                <flux:icon.arrows-pointing-in variant="mini" class="hidden size-4 in-data-expanded:block" />
            </button>
            <button type="button" data-assistant-close aria-label="Kecilkan asisten" title="Kecilkan" class="rounded-full p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-950"><flux:icon.minus variant="mini" class="size-4" /></button>
        </header>

        <div data-assistant-messages role="log" aria-live="polite" class="flex flex-1 flex-col gap-3 overflow-y-auto px-4 py-4 text-sm">
            <div class="max-w-[85%] self-start rounded-2xl rounded-bl-sm bg-stone-100 px-3.5 py-2.5">Halo! Ceritakan properti yang Anda cari. Harga dan ketersediaan diperiksa saat Anda bertanya.</div>
            <div data-assistant-suggestions class="flex flex-wrap gap-2">
                @foreach (['Cari rumah di Kudus di bawah 700 juta', 'Bandingkan pilihan tadi'] as $suggestion)
                    <button type="button" data-assistant-suggestion class="rounded-full border border-stone-200 px-3 py-1.5 text-xs text-stone-700 hover:border-stone-950">{{ $suggestion }}</button>
                @endforeach
            </div>
        </div>

        <form data-assistant-form class="border-t border-stone-200 px-4 pb-3 pt-3">
            <div class="flex items-center gap-2 rounded-full border border-stone-200 py-1 pl-4 pr-1 focus-within:border-stone-950">
                <label for="assistant-message" class="sr-only">Pertanyaan tentang properti</label>
                <input id="assistant-message" data-assistant-input type="text" required maxlength="500" autocomplete="off" placeholder="Tulis pesan…" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none focus:ring-0">
                <button data-assistant-send type="submit" aria-label="Kirim" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-stone-950 text-white hover:bg-stone-800 disabled:opacity-40"><flux:icon.paper-airplane variant="mini" class="size-4" /></button>
            </div>
            <p class="mt-2 text-center text-[11px] text-stone-400">Jangan masukkan data pribadi. Maks. 500 karakter.</p>
        </form>
    </section>
</div>
