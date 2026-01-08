<?php

declare(strict_types=1);

namespace App\Classes;

use Monolog\Logger as MonologLogger;

/**
 * GeminiPredictor - Predicciones de partidos usando Google Gemini AI
 * 
 * Utiliza la API gratuita de Gemini para analizar partidos y generar
 * predicciones basadas en contexto histórico y estadísticas.
 */
class GeminiPredictor
{
    private string $apiKey;
    private ?MonologLogger $logger;
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const RATE_LIMIT_DELAY = 1; // segundos entre llamadas
    private const API_TIMEOUT = 60; // segundos de timeout (aumentado para priorizar IA)
    private const MAX_RETRIES = 2; // reintentos si falla

    private static ?float $lastCallTime = null;

    public function __construct(?string $apiKey = null, ?MonologLogger $logger = null)
    {
        $this->apiKey = $apiKey ?? ($_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: '');
        $this->logger = $logger;
    }

    /**
     * Verifica si la API está configurada
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Analiza un partido y genera predicción + explicación
     * 
     * @param array $matchData Datos del partido (team1, team2, tournament, etc.)
     * @param array $team1Stats Estadísticas históricas del equipo 1
     * @param array $team2Stats Estadísticas históricas del equipo 2
     * @param array $h2hRecord Historial de enfrentamientos directos
     * @return array{prediction: float, explanation: string, source: string}
     */
    public function analyzeMatch(
        array $matchData,
        array $team1Stats = [],
        array $team2Stats = [],
        array $h2hRecord = []
    ): array {
        if (!$this->isConfigured()) {
            $this->log('warning', 'Gemini API key not configured, using fallback');
            return $this->getFallbackResult();
        }

        // Rate limiting
        $this->respectRateLimit();

        // Intentar con reintentos para priorizar IA sobre ELO
        $lastError = '';
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $prompt = $this->buildPrompt($matchData, $team1Stats, $team2Stats, $h2hRecord);
                $response = $this->callGeminiAPI($prompt);

                if ($response) {
                    $result = $this->parseGeminiResponse($response, $matchData);
                    if ($result['source'] === 'gemini') {
                        $this->log('info', "Gemini prediction successful on attempt {$attempt}");
                        return $result;
                    }
                }
                $lastError = 'Invalid response format';
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->log('warning', "Gemini attempt {$attempt} failed: {$lastError}");
            }

            // Esperar antes del siguiente intento
            if ($attempt < self::MAX_RETRIES) {
                sleep(2);
            }
        }

        $this->log('error', "All Gemini attempts failed. Last error: {$lastError}");
        return $this->getFallbackResult();
    }

    /**
     * Construye el prompt para Gemini
     */
    private function buildPrompt(
        array $matchData,
        array $team1Stats,
        array $team2Stats,
        array $h2hRecord
    ): string {
        $team1 = $matchData['team1'] ?? $matchData['team1_name'] ?? 'Team 1';
        $team2 = $matchData['team2'] ?? $matchData['team2_name'] ?? 'Team 2';
        $game = $matchData['game_type'] ?? 'esports';
        $tournament = $matchData['tournament'] ?? $matchData['tournament_name'] ?? 'Tournament';

        $prompt = "You are an esports analyst. Analyze this {$game} match and predict the winner.\n\n";
        $prompt .= "MATCH: {$team1} vs {$team2}\n";
        $prompt .= "TOURNAMENT: {$tournament}\n\n";

        // Estadísticas del equipo 1
        if (!empty($team1Stats)) {
            $prompt .= "=== {$team1} STATS ===\n";
            $prompt .= "Recent matches: {$team1Stats['recent_matches']} (W: {$team1Stats['wins']}, L: {$team1Stats['losses']})\n";
            $prompt .= "Win rate: " . number_format(($team1Stats['win_rate'] ?? 0) * 100, 1) . "%\n";
            if (isset($team1Stats['elo'])) {
                $prompt .= "ELO Rating: {$team1Stats['elo']}\n";
            }
            $prompt .= "\n";
        }

        // Estadísticas del equipo 2
        if (!empty($team2Stats)) {
            $prompt .= "=== {$team2} STATS ===\n";
            $prompt .= "Recent matches: {$team2Stats['recent_matches']} (W: {$team2Stats['wins']}, L: {$team2Stats['losses']})\n";
            $prompt .= "Win rate: " . number_format(($team2Stats['win_rate'] ?? 0) * 100, 1) . "%\n";
            if (isset($team2Stats['elo'])) {
                $prompt .= "ELO Rating: {$team2Stats['elo']}\n";
            }
            $prompt .= "\n";
        }

        // Historial H2H
        if (!empty($h2hRecord)) {
            $prompt .= "=== HEAD TO HEAD ===\n";
            $prompt .= "{$team1}: {$h2hRecord['team1_wins']} wins\n";
            $prompt .= "{$team2}: {$h2hRecord['team2_wins']} wins\n\n";
        }

        $prompt .= "RESPOND IN THIS EXACT JSON FORMAT ONLY (no markdown, no extra text):\n";
        $prompt .= '{"team1_win_probability": <number 0-100>, "analysis": "<brief 1-2 sentence analysis in Spanish>"}';

        return $prompt;
    }

    /**
     * Llama a la API de Gemini
     */
    private function callGeminiAPI(string $prompt): ?array
    {
        $url = self::API_URL . '?key=' . $this->apiKey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1024,
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 30,
            // Desactivar verificación SSL para entornos de desarrollo (WAMP/XAMPP)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            $this->log('error', "cURL error [{$errno}]: {$error}");
            error_log("[GeminiPredictor] cURL error [{$errno}]: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $this->log('error', "Gemini API returned HTTP {$httpCode}");
            error_log("[GeminiPredictor] HTTP {$httpCode}: " . substr($response, 0, 500));
            return null;
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('error', 'Failed to decode Gemini response');
            return null;
        }

        return $decoded;
    }

    /**
     * Parsea la respuesta de Gemini
     */
    private function parseGeminiResponse(array $response, array $matchData): array
    {
        try {
            // Extraer el texto de la respuesta
            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Limpiar posibles caracteres de markdown
            $text = trim($text);
            $text = preg_replace('/^```json\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
            $text = trim($text);

            $parsed = json_decode($text, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['team1_win_probability'])) {
                $probability = (float) $parsed['team1_win_probability'];
                $probability = max(5.0, min(95.0, $probability)); // Clamp entre 5-95%

                $team1 = $matchData['team1'] ?? $matchData['team1_name'] ?? 'Team 1';
                $team2 = $matchData['team2'] ?? $matchData['team2_name'] ?? 'Team 2';

                return [
                    'prediction' => $probability,
                    'explanation' => $parsed['analysis'] ?? "Análisis de {$team1} vs {$team2} generado por IA.",
                    'source' => 'gemini'
                ];
            }
        } catch (\Exception $e) {
            $this->log('error', 'Error parsing Gemini response: ' . $e->getMessage());
        }

        return $this->getFallbackResult();
    }

    /**
     * Resultado de fallback cuando la API no funciona
     */
    private function getFallbackResult(): array
    {
        return [
            'prediction' => 50.0,
            'explanation' => '',
            'source' => 'fallback'
        ];
    }

    /**
     * Respeta el rate limit de la API
     */
    private function respectRateLimit(): void
    {
        if (self::$lastCallTime !== null) {
            $elapsed = microtime(true) - self::$lastCallTime;
            if ($elapsed < self::RATE_LIMIT_DELAY) {
                usleep((int) ((self::RATE_LIMIT_DELAY - $elapsed) * 1000000));
            }
        }
        self::$lastCallTime = microtime(true);
    }

    /**
     * Log helper
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->$level("[GeminiPredictor] " . $message);
        }
    }

    /**
     * Test de conexión a la API
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $this->respectRateLimit();

        try {
            $response = $this->callGeminiAPI('Say "Hello" in one word.');

            if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return [
                    'success' => true,
                    'message' => 'Gemini API connection successful',
                    'response' => $response['candidates'][0]['content']['parts'][0]['text']
                ];
            }

            return ['success' => false, 'error' => 'Invalid API response'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
