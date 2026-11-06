<?php

namespace App\Http\Controllers;

use App\Support\Legal\PrivacyPolicy;
use App\Support\Legal\RefundPolicy;
use App\Support\Legal\Terms;
use Illuminate\View\View;

/**
 * The public legal pages (landing footer Phase 2). Open to everyone —
 * guests, customers and business users — so the routes carry only the
 * "web" group: no auth and no LandingPageRedirect.
 */
class LegalPageController extends Controller
{
    public function terms(): View
    {
        return $this->show(Terms::document(), 'legal.terms');
    }

    public function privacy(): View
    {
        return $this->show(PrivacyPolicy::document(), 'legal.privacy');
    }

    public function refund(): View
    {
        return $this->show(RefundPolicy::document(), 'legal.refund');
    }

    private function show(array $doc, string $current): View
    {
        return view('legal.show', [
            'doc'     => $doc,
            'related' => array_filter([
                'legal.terms'   => 'Terms and Conditions',
                'legal.privacy' => 'Privacy Policy',
                'legal.refund'  => 'Refund Policy',
            ], fn ($route) => $route !== $current, ARRAY_FILTER_USE_KEY),
        ]);
    }
}
