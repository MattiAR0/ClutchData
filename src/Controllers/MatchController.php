<?php

namespace App\Controllers;

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;
use App\Models\MatchModel;
use Exception;

class MatchController
{
    private MatchModel $model;

    public function __construct()
    {
        $this->model = new MatchModel();
    }

    public function index(?string $gameType = null)
    {
        $matches = [];
        $error = $_SESSION['error'] ?? null;
        $message = $_SESSION['message'] ?? null;

        // Pass current filter to view
        $activeTab = $gameType ?? 'all';

        // Limpiar flash messages una vez leídos
        unset($_SESSION['error']);
        unset($_SESSION['message']);

        try {
            $matches = $this->model->getAllMatches($gameType);
        } catch (Exception $e) {
            $error = "No se pudo conectar a la base de datos o leer partidos: " . $e->getMessage();
        }

        // Cargar Vista
        require __DIR__ . '/../../views/home.php';
    }

    public function scrape()
    {
        try {
            // Limpiar datos antiguos antes de scrapear
            $this->model->deleteAllMatches();

            // Instanciar Scrapers
            $scrapers = [
                new ValorantScraper(),
                new LolScraper(),
                new Cs2Scraper()
            ];

            foreach ($scrapers as $scraper) {
                $data = $scraper->scrapeMatches();
                $this->model->saveMatches($data);
            }

            $_SESSION['message'] = "Scraping completado exitosamente.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error durante el scraping: " . $e->getMessage();
        }

        // Redireccionar a la raíz del proyecto (dinámico)
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function reset()
    {
        try {
            $this->model->deleteAllMatches();
            $_SESSION['message'] = "Base de datos limpiada correctamente.";
        } catch (Exception $e) {
            $_SESSION['error'] = "No se pudo limpiar la base de datos: " . $e->getMessage();
        }

        // Redireccionar a la raíz del proyecto (dinámico)
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
        header('Location: ' . $redirectUrl);
        exit;
    }
}
