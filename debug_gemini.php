<?php
/**
 * Simple, direct Gemini API test
 */

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

echo "API Key: " . substr($apiKey, 0, 15) . "...\n\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

$data = json_encode([
    'contents' => [['parts' => [['text' => 'Reply only with "HELLO"']]]],
    'generationConfig' => ['maxOutputTokens' => 10, 'temperature' => 0]
]);

echo "Calling API...\n";
echo "URL: " . substr($url, 0, 80) . "...\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_VERBOSE => true,
]);

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

// Get verbose info
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

echo "=== RESULT ===\n";
echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: "None") . " (errno: $errno)\n\n";

if ($response) {
    $decoded = json_decode($response, true);
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        echo "SUCCESS! Response: " . $decoded['candidates'][0]['content']['parts'][0]['text'] . "\n";
    } elseif (isset($decoded['error'])) {
        echo "API ERROR:\n";
        print_r($decoded['error']);
    } else {
        echo "UNKNOWN RESPONSE:\n";
        echo substr($response, 0, 1000) . "\n";
    }
} else {
    echo "NO RESPONSE\n\n";
    echo "=== VERBOSE LOG ===\n";
    echo $verboseLog;
}
