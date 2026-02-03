<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Http;

/**
 * Web Tool - Enables HTTP requests.
 */
class WebTool implements ToolInterface
{
    private int $timeout;

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    public function name(): string
    {
        return 'web';
    }

    public function description(): string
    {
        return 'Execute HTTP requests (GET, POST). ' .
               'Can load web pages, call APIs, and send data.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'description' => 'HTTP method',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'The URL for the request',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => 'Optional: HTTP headers',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'Optional: Request body for POST/PUT',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'Optional: Timeout in seconds',
                ],
            ],
            'required' => ['method', 'url'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (empty($params['method'])) {
            $errors[] = 'method is required';
        }

        if (empty($params['url'])) {
            $errors[] = 'url is required';
        } elseif (!filter_var($params['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'url must be a valid URL';
        }

        // Block local URLs for security reasons
        if (isset($params['url'])) {
            $host = parse_url($params['url'], PHP_URL_HOST);
            if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0']) ||
                str_starts_with($host ?? '', '192.168.') ||
                str_starts_with($host ?? '', '10.') ||
                str_starts_with($host ?? '', '172.')) {
                $errors[] = 'Local/private network URLs are not allowed';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $method = strtoupper($params['method']);
        $url = $params['url'];
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? [];
        $timeout = $params['timeout'] ?? $this->timeout;

        try {
            $request = Http::timeout($timeout)
                ->withHeaders($headers);

            $response = match($method) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unknown method: {$method}"),
            };

            return [
                'success' => true,
                'result' => [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $this->parseBody($response),
                    'successful' => $response->successful(),
                ],
                'error' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'request_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Parse the response body.
     */
    private function parseBody($response): mixed
    {
        $contentType = $response->header('Content-Type') ?? '';

        // JSON
        if (str_contains($contentType, 'application/json')) {
            return $response->json();
        }

        // Text/HTML - limit size
        $body = $response->body();
        if (strlen($body) > 100000) {
            return substr($body, 0, 100000) . "\n\n[... truncated, total size: " . strlen($body) . " bytes]";
        }

        return $body;
    }
}
