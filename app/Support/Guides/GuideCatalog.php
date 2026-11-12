<?php

namespace App\Support\Guides;

use App\Models\User;
use App\Services\Navigation\Sidebar;
use Illuminate\Http\Request;

/**
 * Which guides a user may read, and how the Help page lays them out
 * (help guides Phase 1).
 *
 * D1: a page's guide is shown only when the user's sidebar shows that page.
 * The sidebar is the rulebook (permission + plan feature + branch lock), so
 * nothing here repeats its checks: we read the built sidebar and keep the
 * guides whose key is in it.
 *
 * Q2 → A: the Help page mirrors the user's sidebar: "Start here", then the
 * top links (Dashboard, My Payslips, pinned pages), then the branch zone and
 * "Whole spa", with the same group names.
 */
final class GuideCatalog
{
    /** Roles that use the business side (Help is not for admins or customers, D8). */
    public const BUSINESS_ROLES = ['owner', 'manager', 'therapist', 'receptionist', 'hr', 'finance', 'hr_head', 'finance_head'];

    public static function isBusinessUser(?User $user): bool
    {
        return $user !== null
            && $user->spa_id !== null
            && $user->hasAnyRole(self::BUSINESS_ROLES);
    }

    /**
     * Help page sections for this user.
     *
     * @return list<array{label: ?string, groups: list<array{label: ?string, guides: list<array>}>}>
     */
    public static function sections(User $user): array
    {
        $nav = Sidebar::forCurrentRequest($user);
        $ctx = new GuideContext($user, $nav);
        $urls = array_column(Sidebar::links($nav), 'url', 'key') + ['profile' => route('profile.edit')];

        $sections = [[
            'label' => 'Start here',
            'groups' => [['label' => null, 'guides' => self::resolveMany(Guides::ALWAYS, $ctx, $urls)]],
        ]];

        $top = self::resolveMany(array_column($nav['top'], 'key'), $ctx, $urls);
        if ($top !== []) {
            $sections[] = ['label' => null, 'groups' => [['label' => null, 'guides' => $top]]];
        }

        foreach ($nav['zones'] as $zone) {
            $groups = [];
            foreach ($zone['items'] as $item) {
                $keys = $item['type'] === 'link' ? [$item['key']] : array_column($item['links'], 'key');
                $guides = self::resolveMany($keys, $ctx, $urls);
                if ($guides === []) {
                    continue;
                }

                $label = $item['type'] === 'group' ? $item['label'] : null;
                $last = array_key_last($groups);

                // Flattened one-link groups sit together, as in the sidebar.
                if ($label === null && $last !== null && $groups[$last]['label'] === null) {
                    array_push($groups[$last]['guides'], ...$guides);
                } else {
                    $groups[] = ['label' => $label, 'guides' => $guides];
                }
            }

            if ($groups !== []) {
                $sections[] = ['label' => $zone['label'], 'groups' => $groups];
            }
        }

        return $sections;
    }

    /** Guide key for the page being shown, or null when it has no guide (D2: then no Help button). */
    public static function currentKey(User $user, ?Request $request = null): ?string
    {
        $request ??= request();

        if (! self::isBusinessUser($user) || $request->routeIs('help.*')) {
            return null;
        }

        foreach (Guides::all() as $key => $guide) {
            if (! empty($guide['routes']) && $request->routeIs(...$guide['routes'])) {
                return $key;
            }
        }

        foreach (Sidebar::links(Sidebar::forCurrentRequest($user)) as $link) {
            if ($link['active'] && Guides::has($link['key'])) {
                return $link['key'];
            }
        }

        return null;
    }

    public static function currentUrl(User $user): ?string
    {
        $key = self::currentKey($user);

        return $key ? route('help.index').'#'.$key : null;
    }

    /** Should the first-login banner show on this request (D4, Q9 → A: every business account once)? */
    public static function showsBanner(?User $user, ?Request $request = null): bool
    {
        $request ??= request();

        if (! self::isBusinessUser($user) || $user->guides_dismissed_at !== null || $request->routeIs('help.*')) {
            return false;
        }

        // Owners mid-setup (trial choice, ended plan, unverified spa) have no Help to go to yet.
        $spa = $user->spa;

        return $spa !== null
            && $spa->verification_status === 'verified'
            && $spa->hasAccess();
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, string>  $urls  page link per guide key ("Go to this page")
     */
    private static function resolveMany(array $keys, GuideContext $ctx, array $urls = []): array
    {
        $out = [];
        foreach ($keys as $key) {
            if ($guide = self::resolve($key, $ctx, $urls[$key] ?? null)) {
                $out[] = $guide;
            }
        }

        return $out;
    }

    /** A guide with the tasks and steps this user can follow; null when none are left. */
    private static function resolve(string $key, GuideContext $ctx, ?string $url = null): ?array
    {
        $guide = Guides::get($key);
        if ($guide === null) {
            return null;
        }

        $tasks = [];
        foreach ($guide['tasks'] ?? [] as $task) {
            if (! $ctx->passes($task['when'] ?? null)) {
                continue;
            }

            $steps = [];
            foreach ($task['steps'] as $step) {
                if (is_array($step)) {
                    if ($ctx->passes($step['when'] ?? null)) {
                        $steps[] = $step['text'];
                    }
                } else {
                    $steps[] = $step;
                }
            }

            if ($steps !== []) {
                $tasks[] = ['title' => $task['title'], 'steps' => $steps];
            }
        }

        $tips = $guide['tips'] ?? [];

        if ($tasks === [] && $tips === []) {
            return null;
        }

        $search = mb_strtolower(implode(' ', [
            $guide['title'],
            $guide['summary'],
            ...array_column($tasks, 'title'),
            ...array_merge([], ...array_column($tasks, 'steps')),
            ...$tips,
        ]));

        return [
            'key' => $key,
            'title' => $guide['title'],
            'summary' => $guide['summary'],
            'url' => $url,
            'tasks' => $tasks,
            'tips' => $tips,
            'images' => array_values(array_filter(
                $guide['images'] ?? [],
                fn ($img) => is_file(public_path($img['src'])),   // D5: a missing screenshot hides its slot
            )),
            'tour' => $guide['tour'] ?? null,
            'search' => $search,
        ];
    }
}
