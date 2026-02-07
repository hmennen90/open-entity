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
    private string $userAgent;

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
        $this->userAgent = 'OpenEntity/1.0 (Autonomous AI Entity; +https://github.com/openentity; compatible) PHP/' . PHP_VERSION;
    }

    public function name(): string
    {
        return 'web';
    }

    public function description(): string
    {
        return 'Execute HTTP requests (GET, POST). ' .
               'Can load web pages, call APIs, and send data. ' .
               'USE WHEN: You have a specific URL to fetch, need to call an API, or want to read a web page found via SearchTool. ' .
               'TIP: Use SearchTool first to find URLs, then WebTool to fetch their content.';
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

        // Block local/private URLs for security reasons
        if (isset($params['url'])) {
            $host = parse_url($params['url'], PHP_URL_HOST);
            if ($host === null || $host === '') {
                $errors[] = 'URL must contain a valid host';
            } elseif ($this->isPrivateHost($host)) {
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

        // Merge default headers with user-provided headers
        $defaultHeaders = [
            'User-Agent' => $this->userAgent,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,application/json,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,de;q=0.8',
        ];
        $headers = array_merge($defaultHeaders, $headers);

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
     * Check if a hostname resolves to a private/local IP address.
     */
    private function isPrivateHost(string $host): bool
    {
        // Check common private hostnames
        $privateHosts = ['localhost', '0.0.0.0', '[::1]'];
        if (in_array(strtolower($host), $privateHosts)) {
            return true;
        }

        // Resolve hostname to IP and check if it's private
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // Also check for IPv6 literal
            $cleanHost = trim($host, '[]');
            if (filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return !filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            }
            return false;
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
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
