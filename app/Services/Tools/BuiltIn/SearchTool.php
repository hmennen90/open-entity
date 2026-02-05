<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Http;

/**
 * Search Tool - Enables web searches via DuckDuckGo.
 *
 * Use this tool to search the web for information.
 * Can optionally fetch the content of search result pages.
 */
class SearchTool implements ToolInterface
{
    private int $timeout;
    private int $fetchTimeout;
    private string $userAgent;

    public function __construct(int $timeout = 15, int $fetchTimeout = 10)
    {
        $this->timeout = $timeout;
        $this->fetchTimeout = $fetchTimeout;
        $this->userAgent = 'OpenEntity/1.0 (Autonomous AI Entity; +https://github.com/openentity)';
    }

    public function name(): string
    {
        return 'search';
    }

    public function description(): string
    {
        return 'Search the web using DuckDuckGo. ' .
               'Returns search results with titles, URLs and snippets. ' .
               'Set fetch_pages=true to also retrieve the full content of each result page.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query',
                ],
                'max_results' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results to return (default: 5, max: 10)',
                ],
                'fetch_pages' => [
                    'type' => 'boolean',
                    'description' => 'If true, fetch the full content of each result page (default: false). Use this when you need to read the actual page content, not just snippets.',
                ],
                'region' => [
                    'type' => 'string',
                    'description' => 'Region for search results (e.g., "de-de" for Germany, "en-us" for US)',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (empty($params['query'])) {
            $errors[] = 'query is required';
        } elseif (strlen($params['query']) < 2) {
            $errors[] = 'query must be at least 2 characters';
        } elseif (strlen($params['query']) > 500) {
            $errors[] = 'query must not exceed 500 characters';
        }

        if (isset($params['max_results'])) {
            if ($params['max_results'] < 1 || $params['max_results'] > 10) {
                $errors[] = 'max_results must be between 1 and 10';
            }
        }

        // When fetching pages, limit max_results to avoid excessive requests
        if (!empty($params['fetch_pages']) && isset($params['max_results']) && $params['max_results'] > 5) {
            $errors[] = 'max_results cannot exceed 5 when fetch_pages is enabled';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $query = $params['query'];
        $fetchPages = $params['fetch_pages'] ?? false;
        $maxResults = min($params['max_results'] ?? ($fetchPages ? 3 : 5), $fetchPages ? 5 : 10);
        $region = $params['region'] ?? 'wt-wt'; // World-wide by default

        try {
            // Use DuckDuckGo HTML search
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'text/html',
                    'Accept-Language' => 'en-US,en;q=0.9,de;q=0.8',
                ])
                ->asForm()
                ->post('https://html.duckduckgo.com/html/', [
                    'q' => $query,
                    'kl' => $region,
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'result' => null,
                    'error' => [
                        'type' => 'search_failed',
                        'message' => 'DuckDuckGo returned status ' . $response->status(),
                    ],
                ];
            }

            $results = $this->parseSearchResults($response->body(), $maxResults);

            // Optionally fetch page contents
            if ($fetchPages && !empty($results)) {
                $results = $this->fetchPageContents($results);
            }

            return [
                'success' => true,
                'result' => [
                    'query' => $query,
                    'results_count' => count($results),
                    'pages_fetched' => $fetchPages,
                    'results' => $results,
                ],
                'error' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'search_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Fetch the content of each search result page.
     */
    private function fetchPageContents(array $results): array
    {
        foreach ($results as &$result) {
            try {
                $response = Http::timeout($this->fetchTimeout)
                    ->withHeaders([
                        'User-Agent' => $this->userAgent,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9,de;q=0.8',
                    ])
                    ->get($result['url']);

                if ($response->successful()) {
                    $body = $response->body();
                    $result['content'] = $this->extractReadableContent($body);
                    $result['fetch_status'] = 'success';
                } else {
                    $result['content'] = null;
                    $result['fetch_status'] = 'failed';
                    $result['fetch_error'] = 'HTTP ' . $response->status();
                }
            } catch (\Exception $e) {
                $result['content'] = null;
                $result['fetch_status'] = 'failed';
                $result['fetch_error'] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Extract readable text content from HTML.
     */
    private function extractReadableContent(string $html): string
    {
        // Remove script and style elements
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html);
        $html = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html);
        $html = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $html);

        // Try to extract main content areas
        $mainContent = '';
        if (preg_match('/<main\b[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            $mainContent = $matches[1];
        } elseif (preg_match('/<article\b[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            $mainContent = $matches[1];
        } elseif (preg_match('/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $mainContent = $matches[1];
        } else {
            // Fall back to body content
            if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $matches)) {
                $mainContent = $matches[1];
            } else {
                $mainContent = $html;
            }
        }

        // Convert to text
        $text = strip_tags($mainContent);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Limit size to avoid huge responses
        $maxLength = 15000;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength) . "\n\n[... content truncated, total length: " . strlen($text) . " characters]";
        }

        return $text;
    }

    /**
     * Parse DuckDuckGo HTML search results.
     */
    private function parseSearchResults(string $html, int $maxResults): array
    {
        $results = [];

        // DuckDuckGo HTML results are in <div class="result"> elements
        // Each result has: <a class="result__a"> for title/URL, <a class="result__snippet"> for description

        // Extract result blocks
        preg_match_all('/<div[^>]*class="[^"]*result[^"]*results_links[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s', $html, $matches);

        if (empty($matches[0])) {
            // Try alternative pattern for result blocks
            preg_match_all('/<div[^>]*class="[^"]*result[^"]*"[^>]*>.*?<a[^>]*class="[^"]*result__a[^"]*"[^>]*href="([^"]*)"[^>]*>([^<]*)<\/a>.*?<a[^>]*class="[^"]*result__snippet[^"]*"[^>]*>([^<]*)/s', $html, $matches, PREG_SET_ORDER);

            foreach (array_slice($matches, 0, $maxResults) as $match) {
                $url = $this->cleanDuckDuckGoUrl($match[1] ?? '');
                $title = trim(strip_tags($match[2] ?? ''));
                $snippet = trim(strip_tags($match[3] ?? ''));

                if (!empty($url) && !empty($title)) {
                    $results[] = [
                        'title' => $title,
                        'url' => $url,
                        'snippet' => $snippet,
                    ];
                }
            }
        }

        // If regex parsing fails, try a simpler approach
        if (empty($results)) {
            $results = $this->parseResultsSimple($html, $maxResults);
        }

        return $results;
    }

    /**
     * Simple fallback parser for search results.
     */
    private function parseResultsSimple(string $html, int $maxResults): array
    {
        $results = [];

        // Find all result links
        preg_match_all('/<a[^>]*class="[^"]*result__a[^"]*"[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/s', $html, $linkMatches, PREG_SET_ORDER);

        // Find all snippets
        preg_match_all('/<a[^>]*class="[^"]*result__snippet[^"]*"[^>]*>(.*?)<\/a>/s', $html, $snippetMatches);

        foreach (array_slice($linkMatches, 0, $maxResults) as $index => $match) {
            $url = $this->cleanDuckDuckGoUrl($match[1] ?? '');
            $title = trim(strip_tags($match[2] ?? ''));
            $snippet = trim(strip_tags($snippetMatches[1][$index] ?? ''));

            if (!empty($url) && !empty($title)) {
                $results[] = [
                    'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                    'url' => $url,
                    'snippet' => html_entity_decode($snippet, ENT_QUOTES, 'UTF-8'),
                ];
            }
        }

        return $results;
    }

    /**
     * Clean DuckDuckGo redirect URLs to get the actual URL.
     */
    private function cleanDuckDuckGoUrl(string $url): string
    {
        // DuckDuckGo wraps URLs in redirect links
        // Format: //duckduckgo.com/l/?uddg=ENCODED_URL&rut=...
        if (str_contains($url, 'duckduckgo.com/l/')) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);
            if (!empty($params['uddg'])) {
                return urldecode($params['uddg']);
            }
        }

        // Handle relative URLs
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }
}
