<?php
/**
 * Debug Gemini API - Test simple desde PHP
 */

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

echo "<h1>Gemini API Debug</h1>";
echo "<pre>";
echo "API Key: " . substr($apiKey, 0, 15) . "...\n\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=" . $apiKey;

$data = json_encode([
    'contents' => [['parts' => [['text' => 'Say "Hello" in one word only.']]]],
    'generationConfig' => ['maxOutputTokens' => 10, 'temperature' => 0]
]);

echo "URL: " . substr($url, 0, 80) . "...\n";
echo "Request size: " . strlen($data) . " bytes\n\n";

// Check if cURL is available
if (!function_exists('curl_init')) {
    echo "ERROR: cURL extension is not installed!\n";
    exit;
}

echo "Testing cURL connection...\n";

$ch = curl_init();

// Enable verbose mode
$verbose = fopen('php://temp', 'w+');

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
    CURLOPT_STDERR => $verbose,
]);

echo "Calling API...\n";
$startTime = microtime(true);

$response = curl_exec($ch);

$endTime = microtime(true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);

// Get verbose output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

curl_close($ch);

echo "\n=== RESULTS ===\n";
echo "Time taken: " . number_format($endTime - $startTime, 2) . " seconds\n";
echo "HTTP Code: $httpCode\n";
echo "cURL Error #$errno: " . ($error ?: "None") . "\n\n";

if ($response) {
    $decoded = json_decode($response, true);
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        echo "✅ SUCCESS!\n";
        echo "Response: " . $decoded['candidates'][0]['content']['parts'][0]['text'] . "\n";
    } elseif (isset($decoded['error'])) {
        echo "❌ API ERROR:\n";
        echo "Code: " . ($decoded['error']['code'] ?? 'N/A') . "\n";
        echo "Message: " . ($decoded['error']['message'] ?? 'N/A') . "\n";
    } else {
        echo "⚠️ UNEXPECTED RESPONSE:\n";
        echo substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ NO RESPONSE RECEIVED\n\n";
    echo "=== VERBOSE LOG ===\n";
    echo $verboseLog;
}

echo "</pre>";
