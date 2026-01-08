<?php
/**
 * Test script for Gemini AI Integration
 * Run: php test_gemini.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Classes\GeminiPredictor;
use App\Classes\MatchPredictor;
use App\Classes\Database;

echo "=== Gemini AI Integration Test ===\n\n";

// Test 1: Check API Key
echo "1. Checking API Key configuration...\n";
$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
if (empty($apiKey)) {
    echo "   ❌ FAIL: GEMINI_API_KEY not found in .env\n";
    exit(1);
}
echo "   ✅ API Key found: " . substr($apiKey, 0, 10) . "...\n\n";

// Test 2: Test GeminiPredictor connection
echo "2. Testing Gemini API connection...\n";
$predictor = new GeminiPredictor();

if (!$predictor->isConfigured()) {
    echo "   ❌ FAIL: GeminiPredictor not configured\n";
    exit(1);
}

$result = $predictor->testConnection();
if ($result['success']) {
    echo "   ✅ Connection successful!\n";
    echo "   Response: " . trim($result['response']) . "\n\n";
} else {
    echo "   ❌ Connection failed: " . ($result['error'] ?? 'Unknown error') . "\n\n";
}

// Test 3: Test match prediction
echo "3. Testing match prediction with AI...\n";
$testMatch = [
    'team1_name' => 'Fnatic',
    'team2_name' => 'G2 Esports',
    'game_type' => 'valorant',
    'tournament_name' => 'VCT Masters'
];

$matchPredictor = new MatchPredictor();
$aiResult = $matchPredictor->predictMatchWithAI($testMatch);

echo "   Match: {$testMatch['team1_name']} vs {$testMatch['team2_name']}\n";
echo "   Source: {$aiResult['source']}\n";
echo "   Prediction: " . number_format($aiResult['prediction'], 1) . "% for {$testMatch['team1_name']}\n";
if (!empty($aiResult['explanation'])) {
    echo "   Explanation: {$aiResult['explanation']}\n";
}

echo "\n";
if ($aiResult['source'] === 'gemini') {
    echo "✅ Gemini AI is working correctly!\n";
} else {
    echo "⚠️  Using ELO fallback (Gemini may have failed)\n";
}

echo "\n=== Test Complete ===\n";
