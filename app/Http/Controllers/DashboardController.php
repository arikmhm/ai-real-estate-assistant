<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $today = now('Asia/Jakarta')->toDateString();
        $prospects = Prospect::query()->visibleTo($user);
        $due = (clone $prospects)
            ->whereNotNull('followup_on')
            ->whereDate('followup_on', '<=', $today)
            ->whereNotIn('stage', ['berhasil', 'gagal']);
        $upcoming = Visit::query()
            ->where('status', 'dijadwalkan')
            ->where('scheduled_at', '>=', now())
            ->whereHas('prospect', fn ($query) => $query->visibleTo($user));

        $data = [
            'isAdmin' => $user->isAdmin(),
            'prospectTotal' => (clone $prospects)->count(),
            'dueCount' => (clone $due)->count(),
            'dueFollowups' => (clone $due)->with('listing')->orderBy('followup_on')->orderBy('id')->limit(5)->get(),
            'upcomingCount' => (clone $upcoming)->count(),
            'upcomingVisits' => (clone $upcoming)->with(['prospect.owner', 'listing'])->orderBy('scheduled_at')->orderBy('id')->limit(5)->get(),
        ];

        if ($user->isAdmin()) {
            $data['publicationCounts'] = $this->counts(Listing::query(), 'publication_status', ['draft', 'published', 'archived']);
            $data['availabilityCounts'] = $this->counts(Listing::query(), 'availability_status', ['available', 'reserved', 'sold']);
            $data['stageCounts'] = $this->counts(Prospect::query(), 'stage', Prospect::STAGES);
            $data['salesCounts'] = User::query()->where('role', UserRole::Sales->value)
                ->withCount(['ownedProspects as prospects_count'])->orderBy('name')->orderBy('id')->get();
        }

        return view('dashboard', $data);
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
