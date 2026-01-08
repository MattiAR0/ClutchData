<?php

declare(strict_types=1);

namespace App\Controllers;

use GuzzleHttp\Client;
use Exception;

class ImageProxyController
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10.0,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Referer' => 'https://liquipedia.net/',
                'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
            ]
        ]);
    }

    public function handle(): void
    {
        $url = $_GET['url'] ?? null;

        if (!$url) {
            http_response_code(400);
            echo "URL parameter is required";
            exit;
        }

        // Validate domain (security: only allow specific domains)
        $allowedDomains = ['liquipedia.net', 'www.liquipedia.net'];
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $allowedDomains)) {
            // Allow relative URLs that might explicitly start with /commons/ (though scrapers should fix this)
            // But for now, we assume scrapers provide absolute URLs usually.
            // If the URL is just path, we prepend liquipedia domain?
            // Let's stick to absolute validation for security first.
            if (strpos($url, '/commons/images') === 0) {
                $url = 'https://liquipedia.net' . $url;
            } else if (!in_array($parsedUrl['host'] ?? '', $allowedDomains)) {
                http_response_code(403);
                echo "Domain not allowed";
                exit;
            }
        }

        try {
            $response = $this->client->request('GET', $url, ['stream' => true]);

            // Forward relevant headers
            $headers = $response->getHeaders();
            if (isset($headers['Content-Type'])) {
                header('Content-Type: ' . $headers['Content-Type'][0]);
            }
            if (isset($headers['Content-Length'])) {
                header('Content-Length: ' . $headers['Content-Length'][0]);
            }
            // Cache control for 1 day
            header('Cache-Control: public, max-age=86400');

            // Clean output buffer to prevent image corruption
            if (ob_get_level())
                ob_end_clean();

            // Output the body
            $body = $response->getBody();
            while (!$body->eof()) {
                echo $body->read(1024);
            }
        } catch (Exception $e) {
            http_response_code(404);
            // Return a placeholder? Or just empty.
            // For debugging, maybe output error
            // echo "Error fetching image";
        }
        exit;
    }
}
