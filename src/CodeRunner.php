<?php

declare(strict_types=1);

final class CodeRunner
{
    private string $baseUrl;
    private string $javaBaseUrl;
    private bool $waitForResult;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) ($_ENV['JUDGE0_URL'] ?? 'http://127.0.0.1:2358'), '/');
        $this->javaBaseUrl = rtrim((string) ($_ENV['JUDGE0_URL_JAVA'] ?? $this->baseUrl), '/');
        $this->waitForResult = strtolower((string) ($_ENV['JUDGE0_WAIT'] ?? 'true')) !== 'false';
    }

    public function run(string $subjectSlug, string $sourceCode, string $stdin = ''): array
    {
        $languageId = $this->languageIdForSubject($subjectSlug);

        if ($languageId === null) {
            return $this->errorResult('Unsupported subject runtime.');
        }

        $maxSourceLength = max(200, (int) ($_ENV['RUNNER_MAX_SOURCE_LENGTH'] ?? 20000));

        if (mb_strlen($sourceCode) > $maxSourceLength) {
            return $this->errorResult('Source is too large for execution.');
        }

        $memoryLimitKb = max(65536, (int) ($_ENV['RUNNER_MEMORY_LIMIT_KB'] ?? 262144));
        $maxRunnerMemoryKb = max(65536, (int) ($_ENV['RUNNER_MAX_MEMORY_LIMIT_KB'] ?? 512000));

        if (strtolower(trim($subjectSlug)) === 'java') {
            $memoryLimitKb = max($memoryLimitKb, (int) ($_ENV['RUNNER_JAVA_MEMORY_LIMIT_KB'] ?? 512000));
        }

        $memoryLimitKb = min($memoryLimitKb, $maxRunnerMemoryKb);

        $payload = [
            'language_id' => $languageId,
            'source_code' => $sourceCode,
            'stdin' => $stdin,
            'cpu_time_limit' => max(1, (int) ($_ENV['RUNNER_CPU_TIME_LIMIT'] ?? 2)),
            'wall_time_limit' => max(1, (int) ($_ENV['RUNNER_WALL_TIME_LIMIT'] ?? 4)),
            'memory_limit' => $memoryLimitKb,
        ];

        if (strtolower(trim($subjectSlug)) === 'python') {
            // Docker Desktop often lacks cgroup memory hierarchy expected by isolate --cg.
            // Enabling per-process limits avoids --cg mode and fixes Python execution locally.
            $payload['enable_per_process_and_thread_time_limit'] = true;
            $payload['enable_per_process_and_thread_memory_limit'] = true;
        }

        $url = $this->runnerBaseUrlForSubject($subjectSlug) . '/submissions/?base64_encoded=false&wait=' . ($this->waitForResult ? 'true' : 'false');
        $response = $this->postJson($url, $payload);

        if (!($response['ok'] ?? false)) {
            return $this->errorResult((string) ($response['error'] ?? 'Runner request failed.'));
        }

        $body = $response['body'] ?? [];
        if (!is_array($body)) {
            return $this->errorResult('Runner returned an invalid response.');
        }

        if (!$this->waitForResult) {
            return $this->errorResult('Async runner mode is not supported yet. Set JUDGE0_WAIT=true.');
        }

        $status = (string) ($body['status']['description'] ?? 'Unknown');
        $statusId = (int) ($body['status']['id'] ?? 0);
        $stdout = (string) ($body['stdout'] ?? '');
        $stderr = (string) ($body['stderr'] ?? '');
        $compileOutput = (string) ($body['compile_output'] ?? '');
        $message = (string) ($body['message'] ?? '');
        $timeSeconds = (float) ($body['time'] ?? 0);
        $memoryKb = (int) ($body['memory'] ?? 0);

        return [
            'success' => $statusId === 3,
            'status' => $status,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'compile_output' => $compileOutput,
            'message' => $message,
            'execution_ms' => (int) round($timeSeconds * 1000),
            'memory_kb' => $memoryKb,
            'error' => $statusId === 3 ? null : $this->bestError($stderr, $compileOutput, $message, $status),
        ];
    }

    private function languageIdForSubject(string $subjectSlug): ?int
    {
        $subjectSlug = strtolower(trim($subjectSlug));

        return match ($subjectSlug) {
            'python' => (int) ($_ENV['JUDGE0_LANGUAGE_ID_PYTHON'] ?? 71),
            'java' => (int) ($_ENV['JUDGE0_LANGUAGE_ID_JAVA'] ?? 62),
            default => null,
        };
    }

    private function runnerBaseUrlForSubject(string $subjectSlug): string
    {
        return strtolower(trim($subjectSlug)) === 'java' ? $this->javaBaseUrl : $this->baseUrl;
    }

    private function postJson(string $url, array $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => max(5, (int) ($_ENV['RUNNER_HTTP_TIMEOUT'] ?? 15)),
            ]);

            $raw = curl_exec($ch);
            $curlErr = curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($raw === false) {
                return ['ok' => false, 'error' => $curlErr !== '' ? $curlErr : 'Failed to connect to runner.'];
            }

            $decoded = json_decode((string) $raw, true);
            if ($http >= 400) {
                $detail = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : (string) $raw;
                return ['ok' => false, 'error' => 'Runner returned HTTP ' . $http . '. ' . $detail];
            }

            return ['ok' => true, 'body' => is_array($decoded) ? $decoded : []];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => max(5, (int) ($_ENV['RUNNER_HTTP_TIMEOUT'] ?? 15)),
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'Failed to connect to runner.'];
        }

        $decoded = json_decode((string) $raw, true);
        return ['ok' => true, 'body' => is_array($decoded) ? $decoded : []];
    }

    private function bestError(string $stderr, string $compileOutput, string $message, string $status): string
    {
        foreach ([$compileOutput, $stderr, $message] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $status !== '' ? $status : 'Execution failed.';
    }

    private function errorResult(string $message): array
    {
        return [
            'success' => false,
            'status' => 'Error',
            'stdout' => '',
            'stderr' => '',
            'compile_output' => '',
            'message' => '',
            'execution_ms' => 0,
            'memory_kb' => 0,
            'error' => $message,
        ];
    }
}
