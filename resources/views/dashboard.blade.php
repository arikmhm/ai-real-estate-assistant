@php
    $rupiah = fn (int $value) => $value >= 1_000_000_000
        ? 'Rp'.number_format($value / 1_000_000_000, 2, ',', '.').' miliar'
        : 'Rp'.number_format($value / 1_000_000, 0, ',', '.').' juta';
    $trendMax = max(1, ...array_map(fn ($month) => max($month['prospects'], $month['visits']), $trend));
    $trendMax = (int) (ceil($trendMax / 5) * 5);
    $stageMax = max(1, ...array_values($stageCounts));
    $sourceMax = max(1, $sourceCounts->max() ?? 0);
    $activityLabels = [
        'created' => 'menambahkan prospek', 'note' => 'mencatat untuk', 'followup' => 'menindaklanjuti',
        'stage_changed' => 'memindahkan tahap', 'visit_created' => 'menjadwalkan kunjungan', 'visit_updated' => 'memperbarui kunjungan',
        'details_updated' => 'memperbarui data', 'followup_changed' => 'mengubah tindak lanjut', 'owner_changed' => 'mengalihkan',
    ];
@endphp

<x-layouts::app title="Dashboard">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <flux:text class="text-sm">{{ now('Asia/Jakarta')->translatedFormat('l, j F Y') }}</flux:text>
                <flux:heading size="xl">Halo, {{ auth()->user()->name }}</flux:heading>
                <flux:text>
                    {{ $isAdmin ? 'Ringkasan pekerjaan seluruh tim.' : 'Ringkasan prospek dan agenda yang Anda tangani.' }}
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:button icon="calendar-days" :href="route('visits.index')" wire:navigate>Agenda</flux:button>
                <flux:button variant="primary" icon="plus" :href="route('prospects.create')" wire:navigate>Tambah prospek</flux:button>
            </div>
        </header>

        <section aria-label="Ringkasan pekerjaan" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => $isAdmin ? 'Prospek aktif' : 'Prospek aktif saya', 'value' => $activeCount, 'note' => $newThisMonth.' masuk bulan ini · '.$prospectTotal.' total', 'icon' => 'users', 'href' => route('prospects.index')],
                ['label' => 'Tindak lanjut jatuh tempo', 'value' => $dueCount, 'note' => $dueCount > 0 ? 'Perlu dihubungi hari ini' : 'Semua sudah tertangani', 'icon' => 'bell-alert', 'href' => route('visits.index')],
                ['label' => 'Kunjungan mendatang', 'value' => $upcomingCount, 'note' => $todayVisitCount.' terjadwal hari ini', 'icon' => 'calendar-days', 'href' => route('visits.index')],
                ['label' => 'Prospek pernah survei', 'value' => $visitedRate.'%', 'note' => 'Prospek dengan kunjungan selesai', 'icon' => 'map-pin', 'href' => route('prospects.index')],
            ] as $tile)
                <a href="{{ $tile['href'] }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $tile['label'] }}</p>
                        <span class="rounded-lg bg-zinc-100 p-1.5 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon :name="$tile['icon']" variant="mini" class="size-4" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $tile['value'] }}</p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $tile['note'] }}</p>
                </a>
            @endforeach
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 lg:col-span-2 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Tren 6 bulan</flux:heading>
                        <p class="mt-1 text-sm text-zinc-500">Prospek masuk dibanding kunjungan yang selesai.</p>
                    </div>
                    <div class="flex gap-4 text-xs text-zinc-600 dark:text-zinc-300" aria-hidden="true">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#2a78d6] dark:bg-[#3987e5]"></span>Prospek masuk</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-[#eb6834] dark:bg-[#d95926]"></span>Kunjungan selesai</span>
                    </div>
                </div>
                <div class="mt-6 flex gap-3" aria-hidden="true">
                    <div class="flex h-48 flex-col justify-between pb-6 text-right text-xs tabular-nums text-zinc-400">
                        <span>{{ $trendMax }}</span><span>{{ $trendMax / 2 }}</span><span>0</span>
                    </div>
                    <div class="relative flex h-48 flex-1 items-end gap-2 sm:gap-4">
                        <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
                            @foreach (range(1, 3) as $line)<div class="border-t border-dashed border-zinc-200 dark:border-zinc-700"></div>@endforeach
                        </div>
                        @foreach ($trend as $month)
                            <div class="group relative flex h-full flex-1 flex-col justify-end">
                                <div class="pointer-events-none absolute -top-2 left-1/2 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-xs text-white opacity-0 shadow transition group-hover:opacity-100 dark:bg-zinc-100 dark:text-zinc-900">
                                    {{ $month['label'] }}: {{ $month['prospects'] }} prospek · {{ $month['visits'] }} kunjungan
                                </div>
                                <div class="flex flex-1 items-end justify-center gap-0.5 rounded-md px-1 group-hover:bg-zinc-100/70 dark:group-hover:bg-zinc-800/60">
                                    <div class="w-full max-w-7 rounded-t-[4px] bg-[#2a78d6] dark:bg-[#3987e5]" style="height: {{ $month['prospects'] / $trendMax * 100 }}%"></div>
                                    <div class="w-full max-w-7 rounded-t-[4px] bg-[#eb6834] dark:bg-[#d95926]" style="height: {{ $month['visits'] / $trendMax * 100 }}%"></div>
                                </div>
                                <span class="mt-2 h-4 text-center text-xs text-zinc-500">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <table class="sr-only">
                    <caption>Prospek masuk dan kunjungan selesai per bulan</caption>
                    <tr><th>Bulan</th><th>Prospek masuk</th><th>Kunjungan selesai</th></tr>
                    @foreach ($trend as $month)<tr><td>{{ $month['label'] }}</td><td>{{ $month['prospects'] }}</td><td>{{ $month['visits'] }}</td></tr>@endforeach
                </table>
            </section>

            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                <flux:heading size="lg">Prospek per tahap</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">Posisi pipeline saat ini.</p>
                <dl class="mt-5 space-y-3">
                    @foreach ($stageCounts as $stage => $count)
                        <div>
                            <div class="flex justify-between gap-3 text-sm">
                                <dt class="text-zinc-700 dark:text-zinc-200">{{ ucfirst($stage) }}</dt>
                                <dd class="font-semibold tabular-nums">{{ $count }}</dd>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-2 rounded-full {{ $stage === 'gagal' ? 'bg-zinc-400 dark:bg-zinc-500' : 'bg-[#2a78d6] dark:bg-[#3987e5]' }}" style="width: {{ $count / $stageMax * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                <flux:heading size="lg">Sumber prospek</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">Kanal asal seluruh prospek.</p>
                <dl class="mt-5 space-y-3">
                    @forelse ($sourceCounts as $source => $count)
                        <div>
                            <div class="flex justify-between gap-3 text-sm">
                                <dt class="truncate text-zinc-700 dark:text-zinc-200">{{ $source }}</dt>
                                <dd class="shrink-0 tabular-nums"><span class="font-semibold">{{ $count }}</span> <span class="text-zinc-500">· {{ round($count / max(1, $prospectTotal) * 100) }}%</span></dd>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-2 rounded-full bg-[#2a78d6] dark:bg-[#3987e5]" style="width: {{ $count / $sourceMax * 100 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">Belum ada prospek.</p>
                    @endforelse
                </dl>
            </section>

            @if ($isAdmin)
                @php($listingTotal = max(1, array_sum($availabilityCounts)))
                <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">Inventaris</flux:heading>
                        <a class="text-sm text-zinc-500 underline hover:text-zinc-800 dark:hover:text-white" href="{{ route('listings.index') }}" wire:navigate>Kelola</a>
                    </div>
                    <p class="mt-4 text-xs text-zinc-500">Nilai unit tersedia di katalog</p>
                    <p class="text-2xl font-semibold tabular-nums">{{ $rupiah($inventoryValue) }}</p>

                    <h3 class="mt-5 text-sm font-medium">Ketersediaan</h3>
                    <div class="mt-2 flex h-3 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                        @foreach (['available' => 'bg-[#2a78d6] dark:bg-[#3987e5]', 'reserved' => 'bg-[#eb6834] dark:bg-[#d95926]', 'sold' => 'bg-[#1baf7a] dark:bg-[#199e70]'] as $status => $color)
                            @if ($availabilityCounts[$status] > 0)<div class="{{ $color }}" style="width: {{ $availabilityCounts[$status] / $listingTotal * 100 }}%"></div>@endif
                        @endforeach
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        @foreach (['available' => 'bg-[#2a78d6] dark:bg-[#3987e5]', 'reserved' => 'bg-[#eb6834] dark:bg-[#d95926]', 'sold' => 'bg-[#1baf7a] dark:bg-[#199e70]'] as $status => $color)
                            <div>
                                <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><span class="size-2 rounded-sm {{ $color }}"></span>{{ App\Enums\AvailabilityStatus::from($status)->label() }}</dt>
                                <dd class="font-semibold tabular-nums">{{ $availabilityCounts[$status] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <h3 class="mt-5 text-sm font-medium">Status publikasi</h3>
                    <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                        @foreach (App\Models\Listing::PUBLICATION_LABELS as $status => $label)
                            <div class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/60">
                                <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                                <dd class="font-semibold tabular-nums">{{ $publicationCounts[$status] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700 {{ $isAdmin ? '' : 'lg:col-span-2' }}">
                <flux:heading size="lg">Aktivitas terbaru</flux:heading>
                <ol class="mt-4 space-y-4">
                    @forelse ($recentActivities as $activity)
                        <li class="flex gap-3">
                            <flux:avatar size="sm" :name="$activity->actor->name" :initials="$activity->actor->initials()" :src="$activity->actor->avatar_path ? asset($activity->actor->avatar_path) : null" />
                            <div class="min-w-0 text-sm">
                                <p class="text-zinc-700 dark:text-zinc-200">
                                    <span class="font-medium">{{ $activity->actor->name }}</span>
                                    {{ $activityLabels[$activity->type] ?? 'memperbarui' }}
                                    <a class="font-medium underline" href="{{ route('prospects.show', $activity->prospect) }}" wire:navigate>{{ $activity->prospect->name }}</a>
                                    @if ($activity->type === 'stage_changed')
                                        ke <flux:badge size="sm" :color="App\Models\Prospect::STAGE_COLORS[$activity->details['to']] ?? 'zinc'">{{ ucfirst($activity->details['to']) }}</flux:badge>
                                    @endif
                                </p>
                                @if ($activity->type === 'note')<p class="truncate text-zinc-500">“{{ $activity->body }}”</p>@endif
                                <p class="text-xs text-zinc-400">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500">Belum ada aktivitas.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">Tindak lanjut jatuh tempo</flux:heading>
                    <a class="text-sm text-zinc-500 underline hover:text-zinc-800 dark:hover:text-white" href="{{ route('visits.index') }}" wire:navigate>Lihat semua</a>
                </div>
                <p class="mt-1 text-sm text-zinc-500">Hingga hari ini, belum selesai.</p>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($dueFollowups as $prospect)
                        @php($late = $prospect->followup_on->lt(now('Asia/Jakarta')->startOfDay()))
                        <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a class="font-medium underline" href="{{ route('prospects.show', $prospect) }}" wire:navigate>{{ $prospect->name }}</a>
                                <p class="truncate text-sm text-zinc-500">{{ $prospect->listing->title }}</p>
                            </div>
                            <flux:badge size="sm" :color="$late ? 'red' : 'amber'">{{ $late ? 'Terlambat · '.$prospect->followup_on->translatedFormat('j M') : 'Hari ini' }}</flux:badge>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-zinc-500">Belum ada tindak lanjut jatuh tempo.</p>
                    @endforelse
                </div>
            </section>
            <section class="min-w-0 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">Jadwal kunjungan</flux:heading>
                    <a class="text-sm text-zinc-500 underline hover:text-zinc-800 dark:hover:text-white" href="{{ route('visits.index') }}" wire:navigate>Lihat semua</a>
                </div>
                <p class="mt-1 text-sm text-zinc-500">Kunjungan mendatang yang dijadwalkan.</p>
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($upcomingVisits as $visit)
                        @php($at = $visit->scheduled_at->timezone('Asia/Jakarta'))
                        <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="w-14 shrink-0 rounded-lg bg-zinc-100 py-1 text-center dark:bg-zinc-800">
                                <p class="text-[0.65rem] uppercase text-zinc-500">{{ $at->translatedFormat('M') }}</p>
                                <p class="text-lg font-semibold leading-tight">{{ $at->format('j') }}</p>
                            </div>
                            <div class="min-w-0">
                                <a class="font-medium underline" href="{{ route('prospects.show', $visit->prospect) }}" wire:navigate>{{ $visit->prospect->name }}</a>
                                <p class="truncate text-sm text-zinc-500">
                                    {{ $at->format('H:i') }} WIB · {{ $visit->listing->title }}@if ($isAdmin) · {{ $visit->prospect->owner->name }}@endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-zinc-500">Belum ada kunjungan mendatang.</p>
                    @endforelse
                </div>
            </section>
        </div>

        @if ($isAdmin)
            <section class="rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-zinc-700">
                <flux:heading size="lg">Prospek per sales</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">Berdasarkan penanggung jawab saat ini; riwayat sales nonaktif tetap dihitung.</p>
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>Sales</flux:table.column>
                        <flux:table.column align="end">Prospek</flux:table.column>
                        <flux:table.column align="end">Aktif</flux:table.column>
                        <flux:table.column align="end">Kunjungan selesai</flux:table.column>
                        <flux:table.column align="end">Booking/berhasil</flux:table.column>
                        <flux:table.column>Beban aktif</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($salesCounts as $sales)
                            <flux:table.row :key="$sales->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$sales->name" :initials="$sales->initials()" :src="$sales->avatar_path ? asset($sales->avatar_path) : null" />
                                        {{ $sales->name }}@unless ($sales->is_active)<span class="text-sm font-normal text-zinc-500">(nonaktif)</span>@endunless
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $sales->prospects_count }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $sales->active_count }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $visitsBySales[$sales->id] ?? 0 }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $sales->won_count }}</flux:table.cell>
                                <flux:table.cell class="w-40">
                                    <div class="h-2 w-32 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-2 rounded-full bg-[#2a78d6] dark:bg-[#3987e5]" style="width: {{ $sales->active_count / max(1, $salesCounts->max('active_count')) * 100 }}%"></div>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="6">Belum ada sales.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif
    </div>
</x-layouts::app>
