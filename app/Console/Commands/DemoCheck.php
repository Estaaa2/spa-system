<?php

namespace App\Console\Commands;

use App\Services\Diagnostics\HostResolver;
use App\Services\Diagnostics\SchedulerHeartbeat;
use App\Services\PayMongo;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pre-demo check (demo safety Phase 1, D5 / Q3 B).
 *
 * Read-only: it sends no mail, creates nothing in PayMongo and writes nothing
 * to the database. Prints PASS / WARN / FAIL lines and exits non-zero on any
 * FAIL, so it can be scripted. Never prints keys or secrets.
 *
 * Run 30 and 10 minutes before the demo:
 *     sudo -u www-data php artisan demo:check
 */
class DemoCheck extends Command
{
    protected $signature = 'demo:check';

    protected $description = 'Read-only pre-demo check: debug mode, PayMongo (DNS, key, webhook), database, storage link, scheduler and queue';

    public const DNS_WARN_SECONDS = 1.0;

    public const PAYMONGO_HOST = 'api.paymongo.com';

    /** @var array{pass:int, warn:int, fail:int} */
    private array $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0];

    public function handle(HostResolver $resolver): int
    {
        $this->line('Levictas pre-demo check  ' . now()->format('Y-m-d H:i:s T'));
        $this->newLine();

        $this->section('App');
        $this->checkApp();

        $this->section('PayMongo');
        $dnsOk = $this->checkDns($resolver);
        $this->checkKey();
        $this->checkPayMongoApi($dnsOk);

        $this->section('Server');
        $this->checkDatabase();
        $this->checkStorageLink();
        $this->checkScheduler();
        $this->checkQueue();

        $this->newLine();
        $summary = sprintf('%d passed, %d warnings, %d failed', $this->counts['pass'], $this->counts['warn'], $this->counts['fail']);

        if ($this->counts['fail'] > 0) {
            $this->error("RESULT: FAIL ({$summary})");

            return self::FAILURE;
        }

        $this->info("RESULT: OK ({$summary})");

        return self::SUCCESS;
    }

    // ── App ──────────────────────────────────────────────────────────────

    private function checkApp(): void
    {
        // Debug on is what put the stack trace in front of the Thesis A panel.
        config('app.debug')
            ? $this->failLine('APP_DEBUG is on: errors would show the Laravel debug page. Set APP_DEBUG=false, then php artisan optimize.')
            : $this->passLine('APP_DEBUG is off');

        app()->environment('production')
            ? $this->passLine('APP_ENV is production')
            : $this->warnLine('APP_ENV is "' . app()->environment() . '", not production');

        app()->configurationIsCached()
            ? $this->passLine('Config is cached')
            : $this->warnLine('Config is not cached. Run: php artisan optimize');

        $url = (string) config('app.url');
        str_starts_with($url, 'https://')
            ? $this->passLine('APP_URL uses https')
            : $this->warnLine('APP_URL is not https. PayMongo return links and webhooks need https.');

        filled(config('services.flutter.web_url'))
            ? $this->passLine('FLUTTER_WEB_URL is set')
            : $this->warnLine('FLUTTER_WEB_URL is empty: app payment redirects would land on the website');
    }

    // ── PayMongo ─────────────────────────────────────────────────────────

    private function checkDns(HostResolver $resolver): bool
    {
        $start = hrtime(true);
        $ips = $resolver->resolve(self::PAYMONGO_HOST);
        $seconds = (hrtime(true) - $start) / 1e9;
        $took = number_format($seconds, 3) . ' s';

        if ($ips === []) {
            $this->failLine('DNS: ' . self::PAYMONGO_HOST . " did not resolve (after {$took}). This is the Thesis A failure: check /etc/resolv.conf.");

            return false;
        }

        $seconds > self::DNS_WARN_SECONDS
            ? $this->warnLine("DNS: " . self::PAYMONGO_HOST . " resolved, but slowly ({$took}). Check the first nameserver in /etc/resolv.conf.")
            : $this->passLine("DNS: " . self::PAYMONGO_HOST . " resolved in {$took}");

        return true;
    }

    private function checkKey(): void
    {
        $mode = PayMongo::mode();
        $key = (string) config("services.paymongo.{$mode}_secret_key");

        $this->passLine("Mode: {$mode}" . ($mode === 'live' ? ' (real money)' : ''));

        if ($key === '') {
            $this->failLine("No secret key set for {$mode} mode (PAYMONGO_" . strtoupper($mode) . '_SECRET_KEY)');

            return;
        }

        // Only the key's kind is checked; the key itself is never printed.
        $expected = "sk_{$mode}_";
        str_starts_with($key, $expected)
            ? $this->passLine("Secret key is set and is a {$mode} key")
            : $this->failLine("The {$mode}-mode secret key does not start with {$expected}: wrong key for this mode");

        filled(PayMongo::webhookSecret($mode === 'live'))
            ? $this->passLine('Webhook signing secret is set')
            : $this->failLine("No webhook signing secret for {$mode} mode: paid bookings would never be confirmed");
    }

    /**
     * One read-only call: list this account's webhooks. It proves the key is
     * accepted (401 = rejected) and shows whether PayMongo will post to this
     * site's webhook URL. Creates nothing.
     */
    private function checkPayMongoApi(bool $dnsOk): void
    {
        if (! Route::has('webhooks.paymongo')) {
            $this->failLine('Webhook route webhooks.paymongo is missing');

            return;
        }

        $webhookUrl = route('webhooks.paymongo');
        $this->passLine("Webhook route: {$webhookUrl}");

        if (! $dnsOk) {
            $this->failLine('PayMongo API: skipped, DNS failed');

            return;
        }

        if (PayMongo::secretKey() === '') {
            $this->failLine('PayMongo API: skipped, no secret key');

            return;
        }

        try {
            $response = PayMongo::http()->get(PayMongo::API_BASE . '/webhooks');
        } catch (ConnectionException $e) {
            $this->failLine('PayMongo API: could not connect (' . $this->shortMessage($e) . ')');

            return;
        }

        if ($response->status() === 401) {
            $this->failLine('PayMongo API: the secret key was rejected (401)');

            return;
        }

        if (! $response->successful()) {
            $this->warnLine('PayMongo API: reachable, but answered HTTP ' . $response->status());

            return;
        }

        $this->passLine('PayMongo API: reachable and the key is accepted (' . $this->ms($response) . ')');

        $hooks = collect((array) $response->json('data'))
            ->map(fn ($hook) => [
                'url' => rtrim((string) data_get($hook, 'attributes.url'), '/'),
                'status' => (string) data_get($hook, 'attributes.status'),
            ]);

        $match = $hooks->firstWhere('url', rtrim($webhookUrl, '/'));

        if (! $match) {
            $this->failLine('PayMongo has no webhook for ' . $webhookUrl . ' (' . $hooks->count() . ' registered): paid bookings would not be confirmed');
        } elseif ($match['status'] !== 'enabled') {
            $this->failLine("PayMongo webhook for this URL is {$match['status']}, not enabled");
        } else {
            $this->passLine('PayMongo webhook is registered for this URL and enabled');
        }
    }

    // ── Server ───────────────────────────────────────────────────────────

    private function checkDatabase(): void
    {
        try {
            DB::select('select 1');
            $this->passLine('Database connection works (' . DB::connection()->getDriverName() . ')');
        } catch (Throwable $e) {
            $this->failLine('Database connection failed: ' . $this->shortMessage($e));
        }
    }

    private function checkStorageLink(): void
    {
        $link = public_path('storage');

        is_link($link) && is_dir($link)
            ? $this->passLine('public/storage link exists')
            : $this->failLine('public/storage link is missing or broken: spa photos would not show. Run: php artisan storage:link');
    }

    private function checkScheduler(): void
    {
        try {
            $last = SchedulerHeartbeat::lastBeat();
        } catch (Throwable $e) {
            $this->warnLine('Scheduler: could not read the cache (' . $this->shortMessage($e) . ')');

            return;
        }

        if (! $last) {
            $this->warnLine('Scheduler: no run recorded yet. If this persists after a few minutes, the cron entry for schedule:run is missing.');

            return;
        }

        $minutes = (int) $last->diffInMinutes(now(), true);

        $minutes <= 5
            ? $this->passLine("Scheduler ran {$minutes} min ago")
            : $this->warnLine("Scheduler last ran {$minutes} min ago: check the cron entry for schedule:run");
    }

    private function checkQueue(): void
    {
        $driver = (string) config('queue.default');

        if ($driver !== 'database') {
            $this->passLine("Queue: {$driver}" . ($driver === 'sync' ? ' (jobs run immediately, no worker needed)' : ''));

            return;
        }

        try {
            $table = (string) config('queue.connections.database.table', 'jobs');

            if (! Schema::hasTable($table)) {
                $this->warnLine("Queue: database driver, but the {$table} table is missing");

                return;
            }

            $oldest = DB::table($table)->min('created_at');
            $waiting = DB::table($table)->count();

            if ($waiting === 0) {
                $this->passLine('Queue: database, nothing waiting');
            } elseif ($oldest && now()->timestamp - (int) $oldest > 600) {
                $this->warnLine("Queue: {$waiting} job(s) waiting for over 10 min. Is a queue worker running? (or use QUEUE_CONNECTION=sync)");
            } else {
                $this->passLine("Queue: database, {$waiting} job(s) waiting");
            }

            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
                $failed > 0
                    ? $this->warnLine("Queue: {$failed} failed job(s) in the last 24 h (php artisan queue:failed)")
                    : $this->passLine('Queue: no failed jobs in the last 24 h');
            }
        } catch (Throwable $e) {
            $this->warnLine('Queue: could not read the jobs table (' . $this->shortMessage($e) . ')');
        }
    }

    // ── Output ───────────────────────────────────────────────────────────

    private function section(string $title): void
    {
        $this->line("<options=bold>{$title}</>");
    }

    private function passLine(string $message): void
    {
        $this->counts['pass']++;
        $this->line("  <fg=green>PASS</>  {$message}");
    }

    private function warnLine(string $message): void
    {
        $this->counts['warn']++;
        $this->line("  <fg=yellow>WARN</>  {$message}");
    }

    private function failLine(string $message): void
    {
        $this->counts['fail']++;
        $this->line("  <fg=red>FAIL</>  {$message}");
    }

    private function shortMessage(Throwable $e): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160, '…');
    }

    private function ms($response): string
    {
        $seconds = $response->transferStats?->getTransferTime();

        return $seconds !== null ? number_format($seconds, 2) . ' s' : 'ok';
    }
}
