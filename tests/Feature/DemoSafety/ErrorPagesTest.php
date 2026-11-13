<?php

namespace Tests\Feature\DemoSafety;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Demo safety Phase 1: branded error pages (D3/D4, Q1 A, Q7 B), and api/*
 * staying JSON. Runs with app.debug = false, like production.
 */
class ErrorPagesTest extends TestCase
{
    private const SECRET = 'SECRET-EXCEPTION-DETAIL-42';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);

        Route::middleware('web')->group(function () {
            Route::get('/__demo/abort/{code}', fn (int $code) => abort($code));
            Route::get('/__demo/boom', fn () => throw new RuntimeException(self::SECRET));
            Route::post('/__demo/form', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
            Route::post('/__demo/post-only', fn () => 'ok');
        });

        Route::middleware('api')->prefix('api')->group(function () {
            Route::get('/__demo/boom', fn () => throw new RuntimeException(self::SECRET));
            Route::get('/__demo/abort/{code}', fn (int $code) => abort($code));
        });
    }

    public static function pages(): array
    {
        return [
            '403' => [403, "You don't have access to this page"],
            '404' => [404, "We couldn't find that page"],
            '419' => [419, 'This page expired'],
            '429' => [429, 'Too many attempts'],
            '500' => [500, 'Something went wrong on our side'],
            '503' => [503, "We're doing maintenance"],
        ];
    }

    /** @dataProvider pages */
    public function test_each_error_page_is_branded_and_standalone(int $code, string $heading): void
    {
        $html = $this->get("/__demo/abort/{$code}")->assertStatus($code)->getContent();

        $this->assertStringContainsString(e($heading), $html);
        $this->assertStringContainsString('Levictas', $html);
        $this->assertStringContainsString('Error ' . $code, $html);
        $this->assertStringNotContainsString('/build/assets/', $html, 'Error pages must not depend on Vite.');
    }

    public function test_a_crash_shows_the_500_page_without_any_exception_detail(): void
    {
        $html = $this->get('/__demo/boom')->assertStatus(500)->getContent();

        $this->assertStringContainsString('Something went wrong on our side', $html);
        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
        $this->assertStringNotContainsString('.php', $html);
    }

    public function test_the_500_page_renders_when_the_database_is_down(): void
    {
        config([
            'database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x'],
            'database.default' => 'broken',
        ]);
        app('db')->purge('broken');

        $html = $this->get('/__demo/boom')->assertStatus(500)->getContent();

        $this->assertStringContainsString('Something went wrong on our side', $html);
    }

    public function test_the_503_page_renders_on_its_own_for_maintenance_mode(): void
    {
        // php artisan down --render="errors::503" pre-renders this view.
        (new \Illuminate\Foundation\Exceptions\RegisterErrorViewPaths)();
        $html = view('errors::503')->render();

        $this->assertStringContainsString(e("We're doing maintenance"), $html);
    }

    public function test_an_unknown_url_is_a_404_not_a_405(): void
    {
        // The CORS catch-all OPTIONS route used to turn every unknown URL into 405.
        $this->get('/this-page-does-not-exist')->assertStatus(404)->assertSee(e("We couldn't find that page"), false);
        $this->get('/api/this-does-not-exist')->assertStatus(404)->assertJson(['message' => 'API endpoint not found']);
        $this->call('OPTIONS', '/anything')->assertOk();
    }

    public function test_other_4xx_codes_use_the_fallback_page(): void
    {
        $html = $this->get('/__demo/post-only')->assertStatus(405)->getContent();

        $this->assertStringContainsString(e("We couldn't complete that request"), $html);
        $this->assertStringContainsString('Error 405', $html);
    }

    public function test_api_errors_stay_json_without_detail(): void
    {
        $this->get('/api/__demo/boom')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['message' => 'Server Error'])
            ->assertDontSee(self::SECRET);

        $this->get('/api/__demo/abort/403')->assertStatus(403)->assertHeader('Content-Type', 'application/json');
        $this->get('/api/__demo/abort/429')->assertStatus(429)->assertHeader('Content-Type', 'application/json');
        $this->get('/api/__demo/no/such/endpoint')->assertStatus(404)->assertJson(['message' => 'API endpoint not found']);
    }

    public function test_a_web_request_that_expects_json_gets_json(): void
    {
        $this->getJson('/__demo/boom')->assertStatus(500)->assertJson(['message' => 'Server Error']);
    }

    // ── 419 (Q7 B) ───────────────────────────────────────────────────────

    public function test_an_expired_form_goes_back_with_input_and_a_message(): void
    {
        $this->from('/login')
            ->post('/__demo/form', ['email' => 'cara@example.com', 'password' => 'hunter2'])
            ->assertRedirect('/login')
            ->assertSessionHas('error')
            ->assertSessionHasInput('email', 'cara@example.com')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_an_expired_upload_form_goes_back_without_flashing_the_file(): void
    {
        $this->from('/profile')
            ->post('/__demo/form', [
                'name' => 'Cara',
                'document' => \Illuminate\Http\UploadedFile::fake()->create('permit.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect('/profile')
            ->assertSessionHasInput('name', 'Cara')
            ->assertSessionMissing('_old_input.document');
    }

    public function test_an_expired_form_without_a_same_site_page_shows_the_419_page(): void
    {
        $this->post('/__demo/form')->assertStatus(419)->assertSee('This page expired');

        $this->withHeader('referer', 'https://evil.example/form')
            ->post('/__demo/form')
            ->assertStatus(419);
    }

    public function test_an_expired_api_or_json_request_gets_json_419(): void
    {
        $this->from('/login')->postJson('/__demo/form')->assertStatus(419)->assertJson(['message' => 'CSRF token mismatch.']);
    }
}
