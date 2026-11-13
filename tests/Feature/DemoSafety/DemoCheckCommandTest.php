<?php

namespace Tests\Feature\DemoSafety;

use App\Services\Diagnostics\HostResolver;
use App\Services\Diagnostics\SchedulerHeartbeat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Demo safety Phase 1: `php artisan demo:check` (D5, Q3 B). Read-only; exit
 * code is non-zero on any FAIL.
 */
class DemoCheckCommandTest extends TestCase
{
    private const KEY = 'sk_test_SUPERSECRETVALUE123';

    private bool $madeLink = false;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        config([
            'app.debug' => false,
            'app.url' => 'https://levictas.example',
            'services.flutter.web_url' => 'https://app.levictas.example',
            'services.paymongo.mode' => 'test',
            'services.paymongo.test_secret_key' => self::KEY,
            'services.paymongo.test_webhook_secret' => 'whsk_test_x',
            'queue.default' => 'sync',
        ]);
        url()->forceRootUrl('https://levictas.example');
        url()->forceScheme('https');

        $this->resolvesTo(['13.0.0.1']);
        SchedulerHeartbeat::beat();

        $link = public_path('storage');
        if (! file_exists($link) && ! is_link($link)) {
            symlink(storage_path('app/public'), $link);
            $this->madeLink = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->madeLink) {
            @unlink(public_path('storage'));
        }

        parent::tearDown();
    }

    private function resolvesTo(array $ips): void
    {
        $this->app->instance(HostResolver::class, new class($ips) extends HostResolver {
            public function __construct(private array $ips) {}

            public function resolve(string $host): array
            {
                return $this->ips;
            }
        });
    }

    private function webhooks(array $hooks, int $status = 200): void
    {
        Http::fake(['api.paymongo.com/v1/webhooks' => Http::response(['data' => $hooks], $status)]);
    }

    private function hook(string $url = 'https://levictas.example/webhooks/paymongo', string $status = 'enabled'): array
    {
        return ['id' => 'hook_1', 'type' => 'webhook', 'attributes' => ['url' => $url, 'status' => $status, 'events' => ['checkout_session.payment.paid']]];
    }

    public function test_all_good_passes_with_exit_code_zero(): void
    {
        $this->webhooks([$this->hook()]);

        $this->artisan('demo:check')
            ->expectsOutputToContain('PASS  APP_DEBUG is off')
            ->expectsOutputToContain('PASS  DNS: api.paymongo.com resolved')
            ->expectsOutputToContain('PASS  PayMongo API: reachable and the key is accepted')
            ->expectsOutputToContain('PASS  PayMongo webhook is registered for this URL and enabled')
            ->expectsOutputToContain('PASS  Database connection works')
            ->expectsOutputToContain('RESULT: OK')
            ->doesntExpectOutputToContain('SUPERSECRETVALUE')
            ->assertExitCode(0);
    }

    public function test_it_only_makes_one_read_only_request(): void
    {
        $this->webhooks([$this->hook()]);

        $this->artisan('demo:check')->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://api.paymongo.com/v1/webhooks');
    }

    public function test_debug_mode_on_fails(): void
    {
        config(['app.debug' => true]);
        $this->webhooks([$this->hook()]);

        $this->artisan('demo:check')
            ->expectsOutputToContain('FAIL  APP_DEBUG is on')
            ->expectsOutputToContain('RESULT: FAIL')
            ->assertExitCode(1);
    }

    public function test_a_dns_failure_fails_and_skips_the_api_call(): void
    {
        $this->resolvesTo([]);
        Http::fake();

        $this->artisan('demo:check')
            ->expectsOutputToContain('FAIL  DNS: api.paymongo.com did not resolve')
            ->expectsOutputToContain('FAIL  PayMongo API: skipped, DNS failed')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_a_rejected_key_fails(): void
    {
        $this->webhooks([], 401);

        $this->artisan('demo:check')
            ->expectsOutputToContain('FAIL  PayMongo API: the secret key was rejected (401)')
            ->assertExitCode(1);
    }

    public function test_a_key_for_the_wrong_mode_fails(): void
    {
        config(['services.paymongo.test_secret_key' => 'sk_live_oops']);
        $this->webhooks([$this->hook()]);

        $this->artisan('demo:check')
            ->expectsOutputToContain('FAIL  The test-mode secret key does not start with sk_test_')
            ->doesntExpectOutputToContain('sk_live_oops')
            ->assertExitCode(1);
    }

    public function test_a_missing_webhook_fails(): void
    {
        $this->webhooks([$this->hook('https://old-site.example/webhooks/paymongo')]);
        $this->artisan('demo:check')->expectsOutputToContain('FAIL  PayMongo has no webhook for https://levictas.example/webhooks/paymongo')->assertExitCode(1);
    }

    public function test_a_disabled_webhook_fails(): void
    {
        $this->webhooks([$this->hook(status: 'disabled')]);
        $this->artisan('demo:check')->expectsOutputToContain('FAIL  PayMongo webhook for this URL is disabled')->assertExitCode(1);
    }

    public function test_a_connection_failure_fails(): void
    {
        Http::fake(['api.paymongo.com/*' => Http::failedConnection('cURL error 7: Failed to connect to api.paymongo.com')]);

        $this->artisan('demo:check')
            ->expectsOutputToContain('FAIL  PayMongo API: could not connect')
            ->assertExitCode(1);
    }

    public function test_a_stale_scheduler_and_non_production_env_only_warn(): void
    {
        \Illuminate\Support\Facades\Cache::forever(SchedulerHeartbeat::CACHE_KEY, now()->subHour()->toIso8601String());
        $this->webhooks([$this->hook()]);

        $this->artisan('demo:check')
            ->expectsOutputToContain('WARN  Scheduler last ran 60 min ago')
            ->expectsOutputToContain('WARN  APP_ENV is "testing"')
            ->expectsOutputToContain('RESULT: OK')
            ->assertExitCode(0);
    }

    public function test_the_scheduler_heartbeat_is_scheduled_every_minute(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => $e->description === 'scheduler-heartbeat');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
