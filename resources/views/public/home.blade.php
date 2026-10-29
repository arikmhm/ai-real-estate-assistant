@extends('layouts.public')

@section('title', 'Temukan properti untuk langkah berikutnya')
@section('description', 'Lihat rumah, tanah, dan bangunan kos yang tersedia dari katalog agensi.')

@section('content')
    {{-- Hero --}}
    <section class="px-4 pt-4 md:px-8">
        {{-- aset: foto hero. Saat foto dipasang, tambahkan overlay gelap dan ubah teks menjadi putih. --}}
        <div
            class="relative mx-auto flex min-h-[26rem] max-w-7xl flex-col items-center justify-center rounded-3xl bg-stone-200 px-6 pb-24 pt-16 text-center md:min-h-[36rem]">
            <h1 class="max-w-3xl text-4xl font-medium leading-[1.05] tracking-tight md:text-6xl">Temukan properti yang sesuai
                dengan rencana Anda</h1>
            <p class="mt-5 max-w-xl text-stone-600">Harga dan ketersediaan langsung dari data agensi. Pilih sales favorit
                Anda dan lanjutkan percakapan lewat WhatsApp.</p>
            <a href="{{ route('properties.index') }}"
                class="mt-8 rounded-full bg-stone-950 px-6 py-3 text-sm font-medium text-white hover:bg-stone-800">Lihat
                properti</a>
        </div>
    </section>

    {{-- Bar pencarian --}}
    <section class="relative z-10 -mt-14 px-8 md:px-16">
        <form method="GET" action="{{ route('properties.index') }}" aria-label="Pencarian cepat"
            class="mx-auto grid max-w-5xl gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-xl shadow-stone-900/5 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto] lg:items-end lg:gap-0 lg:divide-x lg:divide-stone-200 lg:p-3">
            <label class="block text-xs text-stone-500 lg:px-4">Lokasi
                <select name="city"
                    class="mt-1 block w-full border-0 bg-transparent p-0 text-sm font-medium text-stone-950 focus:ring-0">
                    <option value="">Semua lokasi</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs text-stone-500 lg:px-4">Tipe properti
                <select name="type"
                    class="mt-1 block w-full border-0 bg-transparent p-0 text-sm font-medium text-stone-950 focus:ring-0">
                    <option value="">Semua tipe</option>
                    @foreach (['rumah' => 'Rumah', 'tanah' => 'Tanah', 'kos' => 'Bangunan kos'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs text-stone-500 lg:px-4">Harga maksimum
                <select name="price_max"
                    class="mt-1 block w-full border-0 bg-transparent p-0 text-sm font-medium text-stone-950 focus:ring-0">
                    <option value="">Semua harga</option>
                    @foreach ([500000000 => 'Rp 500 juta', 1000000000 => 'Rp 1 miliar', 2000000000 => 'Rp 2 miliar', 5000000000 => 'Rp 5 miliar'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs text-stone-500 lg:px-4">Kamar tidur
                <select name="rooms_min"
                    class="mt-1 block w-full border-0 bg-transparent p-0 text-sm font-medium text-stone-950 focus:ring-0">
                    <option value="">Berapa saja</option>
                    @foreach ([1, 2, 3, 4] as $rooms)
                        <option value="{{ $rooms }}">{{ $rooms }}+</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-center justify-between gap-4 sm:col-span-2 lg:col-span-1 lg:border-0 lg:pl-4">
                <a href="{{ route('properties.index') }}"
                    class="text-xs text-stone-500 underline underline-offset-2 hover:text-stone-950 lg:hidden">Filter
                    lengkap</a>
                <button type="submit"
                    class="rounded-full bg-stone-950 px-6 py-3 text-sm font-medium text-white hover:bg-stone-800">Cari</button>
            </div>
        </form>
    </section>

    {{-- Tentang dan angka ringkas --}}
    <section class="mx-auto grid max-w-7xl gap-10 px-4 py-24 md:px-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div>
            <p class="text-2xl font-medium leading-snug tracking-tight md:text-3xl">Satu katalog untuk semua properti
                agensi. <span class="text-stone-400">Setiap harga dan status diambil dari data terbaru, jadi yang Anda lihat
                    sama dengan yang ditawarkan sales.</span></p>
        </div>
        <dl class="grid grid-cols-3 gap-4 lg:grid-cols-1 lg:gap-6">
            @foreach ([['listings', 'Properti tersedia'], ['cities', 'Kabupaten/kota'], ['types', 'Tipe properti']] as [$key, $label])
                <div class="flex flex-col-reverse justify-end border-t border-stone-200 pt-4">
                    <dt class="mt-1 text-sm text-stone-500">{{ $label }}</dt>
                    <dd class="text-3xl font-medium tracking-tight md:text-4xl">{{ $stats[$key] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Properti terbaru dan penawaran lahan --}}
    <div class="bg-stone-100 py-20">
        <section class="mx-auto max-w-7xl space-y-8 px-4 md:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-2xl tracking-tight md:text-3xl">Properti yang baru tersedia</h2>
                <a href="{{ route('properties.index') }}"
                    class="text-sm text-stone-600 underline underline-offset-4 hover:text-stone-950">Lihat semua
                    properti</a>
            </div>
            @if ($listings->isEmpty())
                <p class="bg-white p-8 text-stone-600">Belum ada properti tersedia. Silakan kembali lagi nanti.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($listings as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            @endif
        </section>

        @if ($landOffers->isNotEmpty())
            <section class="mx-auto max-w-7xl space-y-8 px-4 pt-20 md:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-2xl tracking-tight md:text-3xl">Penawaran lahan kosong</h2>
                    <p class="max-w-sm text-sm text-stone-500 md:text-right">Lahan siap bangun untuk rumah, usaha, atau
                        simpanan aset jangka panjang.</p>
                </div>
                <div class="space-y-6">
                    @foreach ($landOffers as $offer)
                        @php($photo = $offer->media->firstWhere('kind', 'photo'))
                        @php($land = (float) $offer->land_area_m2)
                        <article
                            class="grid bg-white ring-1 ring-stone-200/70 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_minmax(0,1.3fr)]">
                            @if ($photo)
                                <img src="{{ $photo->url }}" alt="Foto utama {{ $offer->title }}"
                                    class="aspect-[4/3] h-full w-full object-cover" loading="lazy">
                            @else
                                {{-- aset: foto lahan --}}
                                <div class="aspect-[4/3] h-full w-full bg-stone-200"><span class="sr-only">Foto belum
                                        tersedia</span></div>
                            @endif
                            <div class="space-y-3 p-5 md:p-6">
                                <div>
                                    <h3 class="text-lg leading-snug">{{ $offer->title }}</h3>
                                    <p class="mt-1 text-xs text-stone-500">
                                        {{ $offer->province }}/{{ $offer->city }}{{ $offer->district ? '/' . $offer->district : '' }}
                                    </p>
                                </div>
                                @if ($land > 0)
                                    <p class="inline-flex items-center gap-1.5 text-xs text-stone-600">
                                        <flux:icon.arrows-pointing-out variant="micro"
                                            class="size-3.5 text-stone-400" />{{ number_format($land, 0, ',', '.') }} m²
                                        tanah</p>
                                @endif
                                <p class="text-sm leading-relaxed text-stone-700">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($offer->description ?? ''), 160) }}</p>
                                <div class="flex flex-wrap gap-2 pt-2">
                                    <a href="{{ route('properties.show', $offer) }}"
                                        class="bg-stone-950 px-4 py-2.5 text-sm text-white hover:bg-stone-800">Lihat
                                        detail</a>
                                    <a href="{{ route('properties.show', $offer) }}#mortgage-title"
                                        class="border border-stone-300 px-4 py-2.5 text-sm hover:border-stone-950">Simulasi
                                        cicilan</a>
                                </div>
                            </div>
                            <dl
                                class="grid grid-cols-3 gap-4 border-t border-stone-200 p-5 md:grid-cols-1 md:content-center md:border-l md:border-t-0 md:p-6">
                                <div class="flex flex-col-reverse justify-end">
                                    <dt class="text-xs text-stone-400">Harga</dt>
                                    <dd class="text-lg md:text-2xl">Rp
                                        {{ number_format($offer->price_idr / 1000000, 0, ',', '.') }} jt</dd>
                                </div>
                                @if ($land > 0)
                                    <div class="flex flex-col-reverse justify-end">
                                        <dt class="text-xs text-stone-400">Luas tanah</dt>
                                        <dd class="text-lg md:text-2xl">{{ number_format($land, 0, ',', '.') }} m²</dd>
                                    </div>
                                    <div class="flex flex-col-reverse justify-end">
                                        <dt class="text-xs text-stone-400">Harga per m²</dt>
                                        <dd class="text-lg md:text-2xl">Rp
                                            {{ number_format($offer->price_idr / $land / 1000, 0, ',', '.') }} rb</dd>
                                    </div>
                                @endif
                            </dl>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Cara kerja --}}
    <section id="cara-kerja" class="mx-auto max-w-7xl scroll-mt-24 px-4 pt-24 md:px-8">
        <div class="grid gap-6 rounded-3xl bg-stone-50 p-4 md:p-6 lg:grid-cols-2">
            <div class="flex flex-col justify-between gap-10 p-4 md:p-6">
                <div>
                    <h2 class="text-2xl tracking-tight md:text-3xl">Dari mencari sampai bertemu sales, dalam tiga langkah
                    </h2>
                </div>
                <ol class="space-y-6">
                    @foreach ([['Cari dan bandingkan', 'Saring berdasarkan lokasi, tipe, harga, dan luas. Setiap detail menampilkan foto, denah, dan simulasi cicilan.'], ['Pilih sales', 'Lihat daftar sales kami dan pilih siapa yang ingin Anda hubungi.'], ['Lanjut lewat WhatsApp', 'Pesan otomatis menyertakan nama dan kode properti, jadi sales langsung tahu yang Anda maksud.']] as [$title, $body])
                        <li class="flex gap-4 border-t border-stone-200 pt-5">
                            <span class="text-sm font-medium text-stone-400">0{{ $loop->iteration }}</span>
                            <div>
                                <p class="font-medium">{{ $title }}</p>
                                <p class="mt-1 text-sm text-stone-600">{{ $body }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
            {{-- aset: ilustrasi cara kerja --}}
            <div aria-hidden="true" class="min-h-72 rounded-2xl bg-stone-200 lg:min-h-full"></div>
        </div>
    </section>

    {{-- Tim sales --}}
    <section id="tim-sales" class="mx-auto max-w-7xl scroll-mt-24 space-y-10 px-4 pt-24 md:px-8">
        <div class="grid gap-4 md:grid-cols-2 md:items-end">
            <div>
                <h2 class="text-2xl tracking-tight md:text-3xl">Pilih sales yang ingin Anda hubungi</h2>
            </div>
            <p class="text-sm text-stone-500 md:justify-self-end md:text-right">Semua sales dapat menawarkan seluruh
                properti di katalog.</p>
        </div>
        @if ($salesContacts->isEmpty())
            <p class="rounded-2xl border border-stone-200 p-8 text-stone-600">Belum ada sales yang dapat dihubungi.</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($salesContacts as $sales)
                    <x-sales-contact :sales="$sales" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl scroll-mt-24 px-4 pt-24 md:px-8">
        <div class="text-center">
            <h2 class="text-2xl tracking-tight md:text-3xl">Yang sering ditanyakan</h2>
        </div>
        <div class="mt-10 divide-y divide-stone-200 border-y border-stone-200">
            @foreach ([['Bagaimana cara menghubungi sales?', 'Pilih sales di halaman ini atau di detail properti, lalu tekan tombol WhatsApp. Percakapan dan jadwal kunjungan diatur langsung bersama sales.'], ['Apakah harga dan ketersediaan selalu terbaru?', 'Katalog hanya menampilkan properti yang diterbitkan dan masih tersedia. Harga dan status dibaca dari data agensi setiap kali halaman dibuka.'], ['Apa itu simulasi cicilan?', 'Perkiraan pokok dan bunga per bulan dengan bunga tetap, dihitung di browser Anda. Hasilnya bukan penawaran atau persetujuan bank.'], ['Apakah saya perlu membuat akun?', 'Tidak. Anda dapat menjelajahi katalog, membuka detail, dan menghubungi sales tanpa mendaftar.']] as [$question, $answer])
                <details class="accordion group py-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                        {{ $question }}
                        <x-accordion-chevron />
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-stone-600">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Banner ajakan --}}
    <section class="mx-auto max-w-7xl px-4 pt-24 md:px-8">
        <div class="grid items-center gap-8 rounded-3xl bg-stone-100 p-6 md:grid-cols-2 md:p-12">
            <div>
                <h2 class="text-3xl font-medium tracking-tight md:text-4xl">Siap melihat pilihan berikutnya?</h2>
                <p class="mt-4 max-w-md text-stone-600">Jelajahi seluruh katalog dengan filter lengkap, lalu hubungi sales
                    saat Anda menemukan yang cocok.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('properties.index') }}"
                        class="rounded-full bg-stone-950 px-6 py-3 text-sm font-medium text-white hover:bg-stone-800">Jelajahi
                        properti</a>
                    <a href="#tim-sales"
                        class="rounded-full border border-stone-300 px-6 py-3 text-sm font-medium hover:border-stone-950">Hubungi
                        sales</a>
                </div>
            </div>
            {{-- aset: foto banner --}}
            <div aria-hidden="true" class="aspect-[4/3] rounded-2xl bg-stone-200"></div>
        </div>
    </section>
@endsection
