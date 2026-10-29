<x-layouts::app title="Dashboard">
    <div class="mx-auto w-full max-w-6xl space-y-7 p-4 sm:p-6">
        <header class="space-y-1">
            <flux:heading size="xl">Dashboard</flux:heading>
            <flux:text>
                {{ $isAdmin ? 'Ringkasan pekerjaan seluruh tim.' : 'Ringkasan prospek dan agenda yang Anda tangani.' }}
            </flux:text>
        </header>

        <section aria-label="Ringkasan pekerjaan" class="grid gap-3 sm:grid-cols-3">
            <a href="{{ route('prospects.index') }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm text-zinc-500">{{ $isAdmin ? 'Semua prospek' : 'Prospek saya' }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $prospectTotal }}</p>
            </a>
            <a href="{{ route('visits.index') }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm text-zinc-500">Tindak lanjut jatuh tempo</p>
                <p class="mt-1 text-3xl font-semibold">{{ $dueCount }}</p>
            </a>
            <a href="{{ route('visits.index') }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm text-zinc-500">Kunjungan mendatang</p>
                <p class="mt-1 text-3xl font-semibold">{{ $upcomingCount }}</p>
            </a>
        </section>

        @if ($isAdmin)
            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="lg">Status publikasi</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500">Seluruh listing, termasuk yang belum tayang.</p>
                    <dl class="mt-4 space-y-2">
                        @foreach (['draft' => 'Draf', 'published' => 'Terbit', 'archived' => 'Arsip'] as $status => $label)
                            <div class="flex justify-between gap-3">
                                <dt>{{ $label }}</dt>
                                <dd class="font-semibold">{{ $publicationCounts[$status] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
                <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="lg">Ketersediaan</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500">Seluruh listing pada semua status publikasi.</p>
                    <dl class="mt-4 space-y-2">
                        @foreach (App\Enums\AvailabilityStatus::cases() as $status)
                            <div class="flex justify-between gap-3">
                                <dt>{{ $status->label() }}</dt>
                                <dd class="font-semibold">{{ $availabilityCounts[$status->value] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
                <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="lg">Prospek per tahap</flux:heading>
                    <dl class="mt-4 space-y-2">
                        @foreach ($stageCounts as $stage => $count)
                            <div class="flex justify-between gap-3">
                                <dt>{{ ucfirst($stage) }}</dt>
                                <dd class="font-semibold">{{ $count }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">Tindak lanjut jatuh tempo</flux:heading><a class="text-sm underline"
                        href="{{ route('visits.index') }}">Lihat semua</a>
                </div>
                <p class="mt-1 text-sm text-zinc-500">Hingga hari ini, belum selesai.</p>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($dueFollowups as $prospect)
                        <div class="py-3 first:pt-0 last:pb-0"><a class="font-medium underline"
                                href="{{ route('prospects.show', $prospect) }}">{{ $prospect->name }}</a>
                            <p class="text-sm text-zinc-500">{{ $prospect->followup_on->format('d/m/Y') }} ·
                                {{ $prospect->listing->title }}</p>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-zinc-500">Belum ada tindak lanjut jatuh tempo.</p>
                    @endforelse
                </div>
            </section>
            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">Jadwal kunjungan</flux:heading><a class="text-sm underline"
                        href="{{ route('visits.index') }}">Lihat semua</a>
                </div>
                <p class="mt-1 text-sm text-zinc-500">Kunjungan mendatang yang dijadwalkan.</p>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($upcomingVisits as $visit)
                        <div class="py-3 first:pt-0 last:pb-0"><a class="font-medium underline"
                                href="{{ route('prospects.show', $visit->prospect) }}">{{ $visit->prospect->name }}</a>
                            <p class="text-sm text-zinc-500">
                                {{ $visit->scheduled_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB ·
                                {{ $visit->listing->title }}@if ($isAdmin)
                                    · {{ $visit->prospect->owner->name }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-zinc-500">Belum ada kunjungan mendatang.</p>
                    @endforelse
                </div>
            </section>
        </div>

        @if ($isAdmin)
            <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="lg">Prospek per sales</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">Berdasarkan penanggung jawab saat ini; riwayat sales nonaktif
                    tetap dihitung.</p>
                <dl class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($salesCounts as $sales)
                        <div class="flex justify-between gap-3 py-2 first:pt-0 last:pb-0">
                            <dt>{{ $sales->name }}@unless ($sales->is_active)
                                <span class="text-sm text-zinc-500">(nonaktif)</span>
                            @endunless
                        </dt>
                        <dd class="font-semibold">{{ $sales->prospects_count }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Belum ada sales.</p>
                @endforelse
            </dl>
        </section>
    @endif
</div>
</x-layouts::app>
