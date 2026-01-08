<?php
/**
 * AJAX endpoint for async AI predictions
 * Returns JSON with prediction, explanation, and source
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Classes\MatchPredictor;
use App\Classes\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    $matchId = (int) ($_GET['match_id'] ?? 0);

    if ($matchId <= 0) {
        echo json_encode(['error' => 'Invalid match ID']);
        exit;
    }

    // Get match data from database
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match) {
        echo json_encode(['error' => 'Match not found']);
        exit;
    }

    // Check if we already have a cached Gemini prediction
    if (!empty($match['ai_explanation']) && $match['ai_source'] === 'gemini') {
        echo json_encode([
            'prediction' => (float) $match['ai_prediction'],
            'explanation' => $match['ai_explanation'],
            'source' => 'gemini',
            'cached' => true
        ]);
        exit;
    }

    // Only generate AI for upcoming matches
    if ($match['match_status'] !== 'upcoming') {
        echo json_encode([
            'prediction' => (float) ($match['ai_prediction'] ?? 50),
            'explanation' => '',
            'source' => 'elo',
            'cached' => true
        ]);
        exit;
    }

    // Generate new AI prediction
    $predictor = new MatchPredictor();
    $result = $predictor->predictMatchWithAI($match);

    // Cache the result if it's from Gemini
    if ($result['source'] === 'gemini') {
        $updateStmt = $db->prepare("
            UPDATE matches 
            SET ai_prediction = ?, ai_explanation = ?, ai_source = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([
            $result['prediction'],
            $result['explanation'],
            $result['source'],
            $matchId
        ]);
    }

    echo json_encode([
        'prediction' => $result['prediction'],
        'explanation' => $result['explanation'],
        'source' => $result['source'],
        'cached' => false
    ]);

} catch (Exception $e) {
    error_log("AI Prediction API error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to generate prediction',
        'prediction' => 50.0,
        'explanation' => '',
        'source' => 'fallback'
    ]);
}
