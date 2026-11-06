<?php

namespace Tests\Feature\Landing;

use App\Models\User;
use App\Support\Legal\Terms;
use Database\Factories\PayrollUserFactory;
use Database\Seeders\HomeVisitTestSeeder;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Landing footer Phase 3 (revised on review): /about, /faq and /contact
 * pages, "Back to home" links, history-friendly contents links, and the
 * footer Company links.
 */
class AboutFaqContactPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);
        Config::set('levictas.social', ['linkedin' => '', 'x' => '', 'github' => '']);
    }

    private function customer(): User
    {
        return User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
    }

    private function footer(string $html): string
    {
        return substr($html, strpos($html, '<footer'), strpos($html, '</footer>') - strpos($html, '<footer'));
    }

    public function test_about_and_faq_are_pages_not_landing_sections(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('id="about"', false)
            ->assertDontSee('id="faq"', false)
            ->assertSee('Why Book With Us');

        foreach (['about' => 'About Us', 'faq' => 'Frequently Asked Questions'] as $route => $title) {
            $this->assertSame(['web'], Route::getRoutes()->getByName($route)->gatherMiddleware());
            $this->get(route($route))->assertOk()->assertSee("<title>{$title} | Levictas</title>", false);
        }

        $this->get(route('faq'))->assertSee('<details class="group">', false);
        $this->get(route('about'))->assertSee('capstone project developed by');
    }

    public function test_public_pages_have_a_back_to_home_link(): void
    {
        foreach (['about', 'faq', 'contact', 'legal.terms', 'legal.privacy', 'legal.refund'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('href="' . url('/') . '"', false)
                ->assertSee('Back to home');
        }
    }

    public function test_legal_contents_links_do_not_add_history_entries(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee("history.replaceState(null, '', link.hash);", false);
    }

    public function test_faq_rules_are_the_exact_terms_sentences(): void
    {
        $cancellation = Terms::sections(['cancellation'])[0]['blocks'];

        $this->get(route('faq'))->assertOk()
            ->assertSee($cancellation[0], false)
            ->assertSee($cancellation[1], false)
            ->assertSee($cancellation[2], false)
            ->assertSee('href="' . route('legal.refund') . '"', false);
    }

    public function test_faq_prices_follow_the_plan_settings(): void
    {
        $this->get(route('faq'))->assertSee('Basic (₱' . number_format(config('plans.basic.monthly_price')) . ' a month');

        Config::set('plans.basic.monthly_price', 999);
        $this->get(route('faq'))->assertSee('Basic (₱999 a month');
    }

    public function test_footer_company_links_work_everywhere(): void
    {
        foreach (['/', route('contact'), route('faq'), route('legal.privacy')] as $url) {
            $footer = $this->footer($this->get($url)->assertOk()->getContent());

            $this->assertStringContainsString('href="' . route('about') . '">About</a>', $footer, $url);
            $this->assertStringContainsString('href="' . route('contact') . '">Contact</a>', $footer, $url);
            $this->assertStringContainsString('href="' . route('faq') . '">FAQ</a>', $footer, $url);
            $this->assertStringNotContainsString('href="#"', $footer, $url);
        }
    }

    public function test_contact_about_and_faq_are_open_to_every_role(): void
    {
        $this->assertSame(['web'], Route::getRoutes()->getByName('contact')->gatherMiddleware());

        $unverified = PayrollUserFactory::new()->create(['email' => 'unverified@levictas.test', 'email_verified_at' => null]);
        $unverified->assignRole('customer');

        $visitors = [
            null,
            $this->customer(),
            $unverified,
            User::where('email', 'owner@levictas.test')->firstOrFail(),
            User::where('email', 'therapist.a1@levictas.test')->firstOrFail(),
        ];

        foreach ($visitors as $user) {
            $user ? $this->actingAs($user) : auth()->logout();

            $this->get(route('contact'))->assertOk()
                ->assertSee('<title>Contact Us | Levictas</title>', false)
                ->assertSee('Who to contact');
            $this->get(route('about'))->assertOk();
            $this->get(route('faq'))->assertOk();
        }
    }

    public function test_contact_page_shows_the_shared_contact_details(): void
    {
        $this->get(route('contact'))->assertOk()
            ->assertSee('[TEAM EMAIL]')
            ->assertSee('[SCHOOL ADDRESS]')
            ->assertDontSee('mailto:', false)
            ->assertDontSee('Follow the team');

        Config::set('levictas.contact.email', 'hello@levictas.example');
        Config::set('levictas.contact.address', 'Dasmariñas, Cavite');
        Config::set('levictas.social.github', 'https://github.com/levictas-team');

        $this->get(route('contact'))->assertOk()
            ->assertSee('href="mailto:hello@levictas.example"', false)
            ->assertSee('Dasmariñas, Cavite')
            ->assertSee('Follow the team')
            ->assertSee('aria-label="Levictas on GitHub"', false)
            ->assertDontSee('Levictas on LinkedIn');
    }
}
