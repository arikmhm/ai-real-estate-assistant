<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const TZ = 'Asia/Jakarta';

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $now = now(self::TZ);
        $today = $now->toDateString();
        $prospects = Prospect::query()->visibleTo($user);
        $visits = Visit::query()->whereHas('prospect', fn ($query) => $query->visibleTo($user));
        $due = (clone $prospects)
            ->whereNotNull('followup_on')
            ->whereDate('followup_on', '<=', $today)
            ->whereNotIn('stage', ['berhasil', 'gagal']);
        $upcoming = (clone $visits)
            ->where('status', 'dijadwalkan')
            ->where('scheduled_at', '>=', now());

        $prospectTotal = (clone $prospects)->count();
        $data = [
            'isAdmin' => $user->isAdmin(),
            'prospectTotal' => $prospectTotal,
            'activeCount' => (clone $prospects)->whereNotIn('stage', ['berhasil', 'gagal'])->count(),
            'newThisMonth' => (clone $prospects)->where('created_at', '>=', $now->startOfMonth()->utc())->count(),
            'visitedRate' => $prospectTotal > 0
                ? (int) round((clone $prospects)->whereHas('visits', fn ($query) => $query->where('status', 'selesai'))->count() / $prospectTotal * 100)
                : 0,
            'dueCount' => (clone $due)->count(),
            'dueFollowups' => (clone $due)->with('listing')->orderBy('followup_on')->orderBy('id')->limit(5)->get(),
            'upcomingCount' => (clone $upcoming)->count(),
            'todayVisitCount' => (clone $visits)->where('status', 'dijadwalkan')
                ->whereBetween('scheduled_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])->count(),
            'upcomingVisits' => (clone $upcoming)->with(['prospect.owner', 'listing'])->orderBy('scheduled_at')->orderBy('id')->limit(5)->get(),
            'stageCounts' => $this->counts(clone $prospects, 'stage', Prospect::STAGES),
            'sourceCounts' => (clone $prospects)->select('source')->selectRaw('COUNT(*) as total')
                ->groupBy('source')->orderByDesc('total')->orderBy('source')->pluck('total', 'source')->map(fn ($total) => (int) $total),
            'trend' => $this->monthlyTrend(clone $prospects, clone $visits),
            'recentActivities' => ProspectActivity::query()
                ->whereHas('prospect', fn ($query) => $query->visibleTo($user))
                ->with(['prospect', 'actor'])->orderByDesc('created_at')->orderByDesc('id')->limit(6)->get(),
        ];

        if ($user->isAdmin()) {
            $data['publicationCounts'] = $this->counts(Listing::query(), 'publication_status', ['draft', 'published', 'archived']);
            $data['availabilityCounts'] = $this->counts(Listing::query(), 'availability_status', ['available', 'reserved', 'sold']);
            $data['inventoryValue'] = (int) Listing::query()->publicCatalog()->sum('price_idr');
            $data['salesCounts'] = User::query()->where('role', UserRole::Sales->value)
                ->withCount([
                    'ownedProspects as prospects_count',
                    'ownedProspects as active_count' => fn ($query) => $query->whereNotIn('stage', ['berhasil', 'gagal']),
                    'ownedProspects as won_count' => fn ($query) => $query->whereIn('stage', ['booking', 'berhasil']),
                ])->orderBy('name')->orderBy('id')->get();
            $data['visitsBySales'] = Visit::query()->where('visits.status', 'selesai')
                ->join('prospects', 'prospects.id', '=', 'visits.prospect_id')
                ->groupBy('prospects.owner_id')->selectRaw('prospects.owner_id, COUNT(*) as total')
                ->pluck('total', 'owner_id')->map(fn ($total) => (int) $total);
        }

        return view('dashboard', $data);
    }

    /**
     * New prospects and completed visits for the last six months, oldest first.
     *
     * shortcut: grouped in PHP from raw timestamps; move to SQL date_trunc once rows reach tens of thousands.
     *
     * @param  Builder<Prospect>  $prospects
     * @param  Builder<Visit>  $visits
     * @return list<array{label: string, prospects: int, visits: int}>
     */
    private function monthlyTrend(Builder $prospects, Builder $visits): array
    {
        $from = now(self::TZ)->startOfMonth()->subMonths(5);
        $month = fn ($at) => $at->timezone(self::TZ)->format('Y-m');
        $created = $prospects->where('created_at', '>=', $from->utc())->pluck('created_at')->countBy($month);
        $visited = $visits->where('status', 'selesai')->where('scheduled_at', '>=', $from->utc())->pluck('scheduled_at')->countBy($month);

        return array_map(function (int $offset) use ($from, $created, $visited): array {
            $key = $from->addMonths($offset)->format('Y-m');

            return [
                'label' => $from->addMonths($offset)->translatedFormat('M'),
                'prospects' => $created[$key] ?? 0,
                'visits' => $visited[$key] ?? 0,
            ];
        }, range(0, 5));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function counts(Builder $query, string $column, array $keys): array
    {
        $counts = $query->select($column)->selectRaw('COUNT(*) as total')
            ->groupBy($column)->pluck('total', $column);

        return array_replace(array_fill_keys($keys, 0), $counts->map(fn ($total) => (int) $total)->all());
    }
}
