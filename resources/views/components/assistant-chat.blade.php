<aside data-assistant-chat data-endpoint="{{ route('assistant.message') }}" class="mx-auto mb-10 max-w-7xl px-5 md:px-8" aria-label="Asisten pencarian properti">
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-xl font-semibold text-emerald-950">Tanya asisten properti</h2><p class="mt-1 text-sm text-emerald-900">Demo dengan data sintetis. Jangan masukkan data pribadi. Harga dan ketersediaan diperiksa saat Anda bertanya.</p></div>
            <button type="button" data-assistant-clear class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-medium hover:bg-white">Hapus percakapan</button>
        </div>
        <div data-assistant-messages role="log" aria-live="polite" class="mt-4 max-h-80 space-y-3 overflow-y-auto rounded-xl bg-white p-4 text-sm"><p class="text-stone-600">Coba: “Cari rumah di Kudus di bawah 700 juta” atau “Bandingkan pilihan tadi”.</p></div>
        <form data-assistant-form class="mt-4 flex flex-col gap-2 sm:flex-row">
            <label for="assistant-message" class="sr-only">Pertanyaan tentang properti</label>
            <input id="assistant-message" data-assistant-input type="text" required maxlength="500" autocomplete="off" placeholder="Tulis kebutuhan properti Anda…" class="min-w-0 flex-1 rounded-lg border border-stone-300 bg-white px-3 py-2">
            <button data-assistant-send type="submit" class="rounded-lg bg-emerald-700 px-5 py-2 font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">Kirim</button>
        </form>
        <p class="mt-2 text-xs text-stone-600">Maksimal 500 karakter per pesan. <a class="underline" href="{{ route('properties.index') }}">Gunakan filter katalog</a> jika asisten tidak tersedia.</p>
    </div>
</aside>
