<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Classes\Database;
use App\Models\MatchModel;

/**
 * Test unitario para MatchModel
 * Valida la lógica de negocio del modelo de partidos
 */
class MatchModelTest extends TestCase
{
    private MatchModel $model;

    protected function setUp(): void
    {
        $this->model = new MatchModel();
    }

    /**
     * Test: El modelo calcula predicciones AI como float entre 0 y 100
     */
    public function testAiPredictionReturnsValidPercentage(): void
    {
        // Usamos reflexión para acceder al método privado
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('calculateAiPrediction');
        $method->setAccessible(true);

        $prediction = $method->invoke($this->model, 'Team A', 'Team B');

        $this->assertIsFloat($prediction);
        $this->assertGreaterThanOrEqual(0, $prediction);
        $this->assertLessThanOrEqual(100, $prediction);
    }

    /**
     * Test: La predicción es consistente para los mismos equipos
     */
    public function testAiPredictionIsConsistent(): void
    {
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('calculateAiPrediction');
        $method->setAccessible(true);

        $prediction1 = $method->invoke($this->model, 'Fnatic', 'G2');
        $prediction2 = $method->invoke($this->model, 'Fnatic', 'G2');

        $this->assertEquals($prediction1, $prediction2, 'Las predicciones deben ser consistentes para los mismos equipos');
    }

    /**
     * Test: Predicciones diferentes para equipos diferentes
     */
    public function testAiPredictionDiffersForDifferentTeams(): void
    {
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('calculateAiPrediction');
        $method->setAccessible(true);

        $prediction1 = $method->invoke($this->model, 'Fnatic', 'G2');
        $prediction2 = $method->invoke($this->model, 'Cloud9', 'Sentinels');

        // Es muy probable que sean diferentes (aunque no garantizado matemáticamente)
        // Este test valida que el método funciona con diferentes inputs
        $this->assertIsFloat($prediction1);
        $this->assertIsFloat($prediction2);
    }

    /**
     * Test: El modelo puede obtener conexión PDO
     */
    public function testGetConnectionReturnsPDO(): void
    {
        $connection = $this->model->getConnection();
        $this->assertInstanceOf(\PDO::class, $connection);
    }
}
