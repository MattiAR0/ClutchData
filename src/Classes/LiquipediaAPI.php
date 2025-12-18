<?php

declare(strict_types=1);

namespace App\Classes;

use GuzzleHttp\Client;
use App\Exceptions\ScrapingException;

/**
 * Client for the Liquipedia MediaWiki API.
 * Used as a fallback when web scraping fails due to rate limiting or other errors.
 * 
 * @see https://liquipedia.net/api-terms-of-use
 */
class LiquipediaAPI
{
    private Client $client;
    private float $lastRequestTime = 0;

    /** Minimum time between requests in seconds (API requirement) */
    private const RATE_LIMIT_SECONDS = 2.0;

    /** Rate limit for action=parse requests (reduced for practical use) */
    private const PARSE_RATE_LIMIT_SECONDS = 5.0;

    /** Base API endpoints for each game */
    private const API_ENDPOINTS = [
        'valorant' => 'https://liquipedia.net/valorant/api.php',
        'lol' => 'https://liquipedia.net/leagueoflegends/api.php',
        'cs2' => 'https://liquipedia.net/counterstrike/api.php',
    ];

    /** Match list pages for each game */
    private const MATCH_PAGES = [
        'valorant' => 'Liquipedia:Matches',
        'lol' => 'Liquipedia:Matches',
        'cs2' => 'Liquipedia:Matches',
    ];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15.0,
            'headers' => [
                'User-Agent' => 'ClutchData-StudentProject/1.0 (https://github.com/student/clutchdata; contact@example.com)',
                'Accept-Encoding' => 'gzip',
            ],
            'verify' => false,
        ]);
    }

    /**
     * Respects Liquipedia's rate limit requirements.
     * Waits if necessary before making the next request.
     *
     * @param bool $isParseAction Whether this is an action=parse request
     */
    private function respectRateLimit(bool $isParseAction = false): void
    {
        $limit = $isParseAction ? self::PARSE_RATE_LIMIT_SECONDS : self::RATE_LIMIT_SECONDS;
        $elapsed = microtime(true) - $this->lastRequestTime;

        if ($elapsed < $limit) {
            $waitTime = $limit - $elapsed;
            usleep((int) ($waitTime * 1000000));
        }

        $this->lastRequestTime = microtime(true);
    }

    /**
     * Make a request to the MediaWiki API.
     *
     * @param string $game Game type (valorant, lol, cs2)
     * @param array $params API parameters
     * @param bool $isParseAction Whether this is a parse action
     * @return array Decoded JSON response
     * @throws ScrapingException On API errors
     */
    private function request(string $game, array $params, bool $isParseAction = false): array
    {
        if (!isset(self::API_ENDPOINTS[$game])) {
            throw ScrapingException::apiError("Unknown game type: $game");
        }

        $this->respectRateLimit($isParseAction);

        $params['format'] = 'json';

        try {
            $response = $this->client->request('GET', self::API_ENDPOINTS[$game], [
                'query' => $params,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($data === null) {
                throw ScrapingException::parseError("Failed to decode API response");
            }

            if (isset($data['error'])) {
                throw ScrapingException::apiError($data['error']['info'] ?? 'Unknown API error');
            }

            return $data;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            if ($statusCode === 429) {
                throw ScrapingException::rateLimited("API rate limited");
            }
            throw ScrapingException::apiError("HTTP $statusCode: " . $e->getMessage());
        } catch (\Exception $e) {
            if ($e instanceof ScrapingException) {
                throw $e;
            }
            throw ScrapingException::apiError($e->getMessage());
        }
    }

    /**
     * Get the raw HTML content of a page via the API.
     * This is useful as a fallback when direct scraping fails.
     *
     * @param string $game Game type (valorant, lol, cs2)
     * @param string $page Page title (e.g., "Liquipedia:Matches")
     * @return string HTML content of the page
     * @throws ScrapingException On errors
     */
    public function getPageHtml(string $game, string $page): string
    {
        $data = $this->request($game, [
            'action' => 'parse',
            'page' => $page,
            'prop' => 'text',
        ], true);

        return $data['parse']['text']['*'] ?? '';
    }

    /**
     * Get matches page HTML for a game.
     * Primary fallback method when web scraping fails.
     *
     * @param string $game Game type (valorant, lol, cs2)
     * @return string HTML content of the matches page
     * @throws ScrapingException On errors
     */
    public function getMatchesHtml(string $game): string
    {
        $page = self::MATCH_PAGES[$game] ?? 'Liquipedia:Matches';
        return $this->getPageHtml($game, $page);
    }

    /**
     * Get match details page HTML.
     *
     * @param string $game Game type
     * @param string $matchPage Match page title (e.g., "Match:12345")
     * @return string HTML content
     * @throws ScrapingException On errors
     */
    public function getMatchDetailsHtml(string $game, string $matchPage): string
    {
        return $this->getPageHtml($game, $matchPage);
    }

    /**
     * Search for pages matching a query.
     *
     * @param string $game Game type
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array Search results
     * @throws ScrapingException On errors
     */
    public function search(string $game, string $query, int $limit = 10): array
    {
        $data = $this->request($game, [
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $query,
            'srlimit' => $limit,
        ]);

        return $data['query']['search'] ?? [];
    }

    /**
     * Get recent changes (useful for detecting new matches).
     *
     * @param string $game Game type
     * @param int $limit Maximum results
     * @return array Recent changes
     * @throws ScrapingException On errors
     */
    public function getRecentChanges(string $game, int $limit = 50): array
    {
        $data = $this->request($game, [
            'action' => 'query',
            'list' => 'recentchanges',
            'rclimit' => $limit,
            'rcnamespace' => 0, // Main namespace
        ]);

        return $data['query']['recentchanges'] ?? [];
    }

    /**
     * Check if the API is accessible and working.
     *
     * @param string $game Game type to test
     * @return bool True if API is working
     */
    public function healthCheck(string $game = 'valorant'): bool
    {
        try {
            $data = $this->request($game, [
                'action' => 'query',
                'meta' => 'siteinfo',
            ]);
            return isset($data['query']['general']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
