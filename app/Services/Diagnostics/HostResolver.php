<?php

namespace App\Services\Diagnostics;

/**
 * Looks up a host name with the server's own resolver (the same one cURL
 * uses), so demo:check sees what PayMongo calls will see. A class of its own
 * so tests can swap it for a fake.
 */
class HostResolver
{
    /** @return array<int, string> IPv4 addresses; empty when the lookup failed */
    public function resolve(string $host): array
    {
        $ips = @gethostbynamel($host);

        return is_array($ips) ? $ips : [];
    }
}
