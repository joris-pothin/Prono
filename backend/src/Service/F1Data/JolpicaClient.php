<?php

namespace App\Service\F1Data;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for jolpica-f1 (https://github.com/jolpica/jolpica-f1), the community-run
 * successor to the now-frozen Ergast API. Same response shape as Ergast, so swapping
 * providers later mostly means changing $baseUri and, if needed, the JSON parsing in
 * App\Command\SyncF1DataCommand.
 */
final class JolpicaClient
{
    private const string BASE_URI = 'https://api.jolpi.ca/ergast/f1';
    /** Minimum gap between two requests, to stay under the public instance's rate limit. */
    private const int MIN_INTERVAL_MICROSECONDS = 1_000_000;

    private float $lastRequestAt = 0.0;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    public function fetch(string $path, array $query = []): array
    {
        $query += ['limit' => 100];

        // The public jolpica-f1 instance is rate-limited; pace our own calls and back off
        // with retries on HTTP 429 instead of failing a whole sync run over one throttled call.
        $attempt = 0;
        while (true) {
            $this->throttle();
            $response = $this->httpClient->request('GET', self::BASE_URI.$path, ['query' => $query]);
            $status = $response->getStatusCode();

            if (429 !== $status) {
                return $response->toArray(false);
            }

            if (++$attempt > 8) {
                $response->toArray(); // let it throw its normal exception
            }

            usleep(2_000_000 * $attempt);
        }
    }

    private function throttle(): void
    {
        $elapsedMicroseconds = (microtime(true) - $this->lastRequestAt) * 1_000_000;
        if ($elapsedMicroseconds < self::MIN_INTERVAL_MICROSECONDS) {
            usleep((int) (self::MIN_INTERVAL_MICROSECONDS - $elapsedMicroseconds));
        }
        $this->lastRequestAt = microtime(true);
    }
}
