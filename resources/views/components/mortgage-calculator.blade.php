@props(['listing'])

<section data-mortgage-calculator aria-labelledby="mortgage-title" class="rounded-3xl border border-stone-200 p-5 md:p-8">
    <div class="max-w-3xl space-y-2">
        <h2 id="mortgage-title" class="text-xl font-medium">Simulasi cicilan</h2>
        <p class="text-sm text-stone-600">Ubah angka sesuai rencana Anda. Perhitungan dilakukan di browser dan tidak disimpan.</p>
    </div>

    <form data-mortgage-form novalidate autocomplete="off" class="mt-5 space-y-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block space-y-1 text-sm font-medium">Harga properti (Rp)
                <input data-mortgage-price value="{{ $listing->price_idr }}" type="number" min="1" max="100000000000" step="1" inputmode="numeric" class="w-full rounded-xl border border-stone-200 px-3 py-2.5 font-normal focus:border-stone-950 focus:outline-none focus:ring-1 focus:ring-stone-950" aria-describedby="mortgage-error">
            </label>
            <label class="block space-y-1 text-sm font-medium">Uang muka (Rp)
                <input data-mortgage-amount type="number" min="0" step="1" inputmode="numeric" placeholder="Masukkan nominal" class="w-full rounded-xl border border-stone-200 px-3 py-2.5 font-normal focus:border-stone-950 focus:outline-none focus:ring-1 focus:ring-stone-950" aria-describedby="mortgage-error">
            </label>
            <label class="block space-y-1 text-sm font-medium">Uang muka (%)
                <input data-mortgage-percent type="number" min="0" max="99.99" step="0.01" inputmode="decimal" placeholder="Atau persentase" class="w-full rounded-xl border border-stone-200 px-3 py-2.5 font-normal focus:border-stone-950 focus:outline-none focus:ring-1 focus:ring-stone-950" aria-describedby="mortgage-error">
            </label>
            <label class="block space-y-1 text-sm font-medium">Tenor (tahun)
                <input data-mortgage-years type="number" min="1" max="30" step="1" inputmode="numeric" placeholder="1–30" class="w-full rounded-xl border border-stone-200 px-3 py-2.5 font-normal focus:border-stone-950 focus:outline-none focus:ring-1 focus:ring-stone-950" aria-describedby="mortgage-error">
            </label>
            <label class="block space-y-1 text-sm font-medium sm:col-span-2 lg:col-span-1">Bunga tahunan (%)
                <input data-mortgage-rate type="number" min="0" max="30" step="0.01" inputmode="decimal" placeholder="0–30" class="w-full rounded-xl border border-stone-200 px-3 py-2.5 font-normal focus:border-stone-950 focus:outline-none focus:ring-1 focus:ring-stone-950" aria-describedby="mortgage-error">
            </label>
        </div>
        <p id="mortgage-error" data-mortgage-error role="alert" hidden class="rounded-xl bg-red-50 p-3 text-sm text-red-800"></p>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-full bg-stone-950 px-6 py-2.5 text-sm font-medium text-white hover:bg-stone-800">Hitung cicilan</button>
            <button type="reset" class="rounded-full border border-stone-300 px-6 py-2.5 text-sm font-medium hover:border-stone-950">Reset</button>
        </div>
    </form>

    <div data-mortgage-result aria-live="polite" hidden class="mt-6 rounded-2xl bg-stone-50 p-5">
        <h3 class="text-sm text-stone-500">Estimasi pokok dan bunga per bulan</h3>
        <p data-mortgage-output="monthlyPayment" class="mt-1 text-3xl font-semibold text-stone-950"></p>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-stone-600">Harga simulasi</dt><dd data-mortgage-output="price" class="font-semibold"></dd></div>
            <div><dt class="text-stone-600">Uang muka</dt><dd data-mortgage-output="downPayment" class="font-semibold"></dd></div>
            <div><dt class="text-stone-600">Pokok pinjaman</dt><dd data-mortgage-output="principal" class="font-semibold"></dd></div>
            <div><dt class="text-stone-600">Tenor dan bunga</dt><dd data-mortgage-output="terms" class="font-semibold"></dd></div>
        </dl>
    </div>

    <p class="mt-5 max-w-3xl text-xs leading-relaxed text-stone-600">Simulasi menganggap bunga tetap sepanjang tenor dan hanya menghitung pokok serta bunga. Pajak, asuransi, biaya administrasi, provisi, dan biaya lain belum dihitung. Hasil ini bukan penawaran atau persetujuan bank.</p>
    <noscript><p class="mt-3 text-sm text-red-800">Aktifkan JavaScript untuk menghitung simulasi di browser.</p></noscript>
</section>
