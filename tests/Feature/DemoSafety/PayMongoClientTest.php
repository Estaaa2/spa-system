<?php

namespace Tests\Feature\DemoSafety;

use App\Services\PayMongo;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Demo safety Phase 1: the shared PayMongo client (D6 retry rule, timeouts).
 */
class PayMongoClientTest extends TestCase
{
    private const URL = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config(['services.paymongo.mode' => 'test', 'services.paymongo.test_secret_key' => 'sk_test_abc']);
    }

    public function test_it_sends_the_mode_key_json_and_timeouts(): void
    {
        Http::fake([self::URL => Http::response(['data' => ['id' => 'cs_1']], 200)]);

        $pending = PayMongo::http();
        $options = $pending->getOptions();

        $this->assertSame(PayMongo::CONNECT_TIMEOUT, $options['connect_timeout']);
        $this->assertSame(PayMongo::TIMEOUT, $options['timeout']);

        $pending->post(self::URL, ['a' => 1]);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic ' . base64_encode('sk_test_abc:'))
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_a_dns_failure_is_retried_once_then_succeeds(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->pushFailedConnection('cURL error 28: Resolving timed out after 5000 milliseconds')
            ->push(['data' => ['id' => 'cs_1']], 200)]);

        $response = PayMongo::http()->post(self::URL, []);

        $this->assertTrue($response->successful());
        Http::assertSentCount(2);
    }

    public function test_a_pre_send_failure_is_retried_only_once(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->pushFailedConnection('cURL error 6: Could not resolve host: api.paymongo.com')
            ->pushFailedConnection('cURL error 6: Could not resolve host: api.paymongo.com')
            ->push(['data' => ['id' => 'cs_never']], 200)]);

        try {
            PayMongo::http()->post(self::URL, []);
            $this->fail('Expected a ConnectionException after the single retry.');
        } catch (ConnectionException) {
            Http::assertSentCount(2);
        }
    }

    public function test_a_timeout_after_sending_is_never_retried(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out after 15001 milliseconds with 0 bytes received')
            ->push(['data' => ['id' => 'cs_duplicate']], 200)]);

        $this->expectException(ConnectionException::class);

        try {
            PayMongo::http()->post(self::URL, []);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_an_error_response_is_returned_not_retried_or_thrown(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['errors' => [['detail' => 'bad']]], 400)
            ->push(['data' => ['id' => 'cs_x']], 200)]);

        $response = PayMongo::http()->post(self::URL, []);

        $this->assertSame(400, $response->status());
        Http::assertSentCount(1);
    }

    public function test_the_retry_rule_reads_curl_errno_from_guzzle(): void
    {
        $guzzle = fn (int $errno, string $msg) => new ConnectionException($msg, 0,
            new ConnectException($msg, new PsrRequest('POST', self::URL), null, ['errno' => $errno]));

        $this->assertTrue(PayMongo::failedBeforeSending($guzzle(6, 'Could not resolve host')));
        $this->assertTrue(PayMongo::failedBeforeSending($guzzle(7, 'Failed to connect')));
        $this->assertTrue(PayMongo::failedBeforeSending($guzzle(28, 'Resolving timed out after 5000 milliseconds')));
        $this->assertTrue(PayMongo::failedBeforeSending($guzzle(28, 'Connection timed out after 5001 milliseconds')));
        $this->assertFalse(PayMongo::failedBeforeSending($guzzle(28, 'Operation timed out after 15000 milliseconds with 0 bytes received')));
        $this->assertFalse(PayMongo::failedBeforeSending($guzzle(35, 'SSL connect error')));
        $this->assertFalse(PayMongo::failedBeforeSending($guzzle(52, 'Empty reply from server')));
        $this->assertFalse(PayMongo::failedBeforeSending(new \RuntimeException('something else')));
    }
}
