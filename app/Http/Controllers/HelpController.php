<?php

namespace App\Http\Controllers;

use App\Support\Guides\GuideCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Help & Guides (help guides Phase 1). Business users only (D8); the route's
 * role middleware already refuses admins and customers, and this re-checks
 * the spa link.
 */
class HelpController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(GuideCatalog::isBusinessUser($user), 403);

        // Q8 → A: once someone opens Help, the "New here?" banner has done its job.
        $this->dismiss($user);

        return view('help.index', [
            'sections' => GuideCatalog::sections($user),
        ]);
    }

    public function dismissBanner(Request $request): RedirectResponse|Response
    {
        $user = $request->user();
        abort_unless(GuideCatalog::isBusinessUser($user), 403);

        $this->dismiss($user);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    private function dismiss($user): void
    {
        if ($user->guides_dismissed_at === null) {
            $user->forceFill(['guides_dismissed_at' => now()])->save();
        }
    }
}
