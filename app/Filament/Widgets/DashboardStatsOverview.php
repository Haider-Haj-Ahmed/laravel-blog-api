<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class DashboardStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    // Refresh automatically every 5 minutes so the numbers stay reasonably live
    // without hammering the DB on every dashboard load.
    protected ?string $pollingInterval = '300s';

    protected function getStats(): array
    {
        $data = Cache::remember('dashboard-stats-overview', now()->addMinutes(5), function () {
            return [
                'new_users' => $this->getNewUsersStat(),
                'trending_tag' => $this->getTrendingTagStat(),
                'posts_published' => $this->getPostsPublishedStat(),
            ];
        });

        return [
            $this->buildNewUsersStat($data['new_users']),
            $this->buildTrendingTagStat($data['trending_tag']),
            $this->buildPostsPublishedStat($data['posts_published']),
        ];
    }

    /**
     * Card 1: what percentage of the whole user base signed up this month.
     */
    private function getNewUsersStat(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $totalUsers = User::count();
        $newThisMonth = User::where('created_at', '>=', $startOfMonth)->count();
        $newLastMonth = User::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

        $percentage = $totalUsers > 0
            ? round(($newThisMonth / $totalUsers) * 100, 1)
            : 0.0;

        return [
            'percentage' => $percentage,
            'new_this_month' => $newThisMonth,
            'new_last_month' => $newLastMonth,
        ];
    }

    private function buildNewUsersStat(array $data): Stat
    {
        $trendUp = $data['new_this_month'] >= $data['new_last_month'];

        return Stat::make('New Users This Month', "{$data['percentage']}%")
            ->description("{$data['new_this_month']} new sign-ups (vs {$data['new_last_month']} last month)")
            ->descriptionIcon($trendUp ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->descriptionColor($trendUp ? 'success' : 'danger')
            ->color('primary')
            ->icon(Heroicon::Users);
    }

    /**
     * Card 2: the tag with the most posts created this month.
     */
    private function getTrendingTagStat(): ?array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $tag = Tag::withCount(['posts' => function ($query) use ($startOfMonth) {
            $query->where('posts.created_at', '>=', $startOfMonth);
        }])
            ->orderByDesc('posts_count')
            ->first();

        if (! $tag || $tag->posts_count === 0) {
            return null;
        }

        return [
            'name' => $tag->name,
            'count' => $tag->posts_count,
        ];
    }

    private function buildTrendingTagStat(?array $data): Stat
    {
        if ($data === null) {
            return Stat::make('Trending Tag', 'No data yet')
                ->description('No posts tagged this month')
                ->color('gray')
                ->icon(Heroicon::Tag);
        }

        return Stat::make('Trending Tag', '#'.$data['name'])
            ->description("{$data['count']} posts this month")
            ->descriptionIcon(Heroicon::ArrowTrendingUp)
            ->descriptionColor('success')
            ->color('warning')
            ->icon(Heroicon::Tag);
    }

    /**
     * Card 3: published posts this month, with a 7-day sparkline and
     * a comparison against last month so it reads as an activity/engagement signal.
     */
    private function getPostsPublishedStat(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $thisMonth = Post::where('is_published', true)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $lastMonth = Post::where('is_published', true)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $chart = collect(range(6, 0))
            ->map(fn (int $daysAgo) => Post::where('is_published', true)
                ->whereDate('created_at', Carbon::now()->subDays($daysAgo))
                ->count())
            ->all();

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'chart' => $chart,
        ];
    }

    private function buildPostsPublishedStat(array $data): Stat
    {
        $trendUp = $data['this_month'] >= $data['last_month'];

        $trendLabel = $trendUp ? 'Up' : 'Down';

        return Stat::make('Posts Published This Month', (string) $data['this_month'])
            ->description("{$trendLabel} from {$data['last_month']} last month")
            ->descriptionIcon($trendUp ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->descriptionColor($trendUp ? 'success' : 'danger')
            ->chart($data['chart'])
            ->chartColor($trendUp ? 'success' : 'danger')
            ->color('info')
            ->icon(Heroicon::DocumentText);
    }
}
