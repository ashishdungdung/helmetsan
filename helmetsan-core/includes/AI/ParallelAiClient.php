<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Executes multiple AI requests in parallel using curl_multi.
 */
final class ParallelAiClient
{
    /**
     * @param array<string, array{url: string, headers: array<string, string>, body: string}> $requests Map of providerId -> request data
     * @param int $timeout Total timeout in seconds
     * @param int $maxConcurrency Max simultaneous requests (for all providers combined)
     * @return array<string, string|null> Map of providerId -> raw response body or null on failure
     */
    public function execute(array $requests, int $timeout = 30, int $maxConcurrency = 8): array
    {
        if ($requests === []) {
            return [];
        }

        $finalResults = array_fill_keys(array_keys($requests), null);
        $chunks = array_chunk($requests, max(1, $maxConcurrency), true);

        foreach ($chunks as $chunk) {
            $attempt = 0;
            $maxRetries = 2;
            $currentChunk = $chunk;

            while ($currentChunk !== [] && $attempt <= $maxRetries) {
                if ($attempt > 0) {
                    sleep((int) pow(2, $attempt)); 
                }

                $mh = curl_multi_init();
                if ($mh === false) {
                    break;
                }

                $handles = [];
                foreach ($currentChunk as $id => $req) {
                    $ch = curl_init($req['url']);
                    if ($ch === false) {
                        continue;
                    }

                    $headers = [];
                    foreach ($req['headers'] as $k => $v) {
                        $headers[] = "$k: $v";
                    }

                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $req['body']);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

                    curl_multi_add_handle($mh, $ch);
                    $handles[$id] = $ch;
                }

                $active = null;
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc === CURLM_OK && $active > 0);

                while ($active && $mrc === CURLM_OK) {
                    if (curl_multi_select($mh) === -1) {
                        usleep(100);
                    }
                    do {
                        $mrc = curl_multi_exec($mh, $active);
                    } while ($mrc === CURLM_OK && $active > 0);
                }

                $nextChunk = [];
                foreach ($handles as $id => $ch) {
                    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($code === 200) {
                        $finalResults[$id] = curl_multi_getcontent($ch);
                    } elseif ($code === 429 && $attempt < $maxRetries) {
                        $nextChunk[$id] = $currentChunk[$id];
                    }
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                }

                curl_multi_close($mh);
                $currentChunk = $nextChunk;
                $attempt++;
            }
        }

        return $finalResults;
    }
}
