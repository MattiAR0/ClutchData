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

    public function index()
    {
        $matches = [];
        $error = null;
        $message = null;

        try {
            $matches = $this->model->getAllMatches();
        } catch (Exception $e) {
            $error = "No se pudo conectar a la base de datos o leer partidos: " . $e->getMessage();
        }

        // Cargar Vista
        require __DIR__ . '/../../views/home.php';
    }

    public function scrape()
    {
        $matches = [];
        $error = null;
        $message = null;

        try {
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

            $message = "Scraping completado exitosamente.";
        } catch (Exception $e) {
            $error = "Error durante el scraping: " . $e->getMessage();
        }

        // Recargar datos para mostrar
        try {
            $matches = $this->model->getAllMatches();
        } catch (Exception $e) {
            // Si ya teníamos un error de scraping, concatenamos o priorizamos
            if (!$error) {
                $error = "Error al recargar datos: " . $e->getMessage();
            }
        }

        require __DIR__ . '/../../views/home.php';
    }

    public function reset()
    {
        $matches = [];
        $error = null;
        $message = null;

        try {
            $this->model->deleteAllMatches();
            $message = "Base de datos limpiada correctamente.";
        } catch (Exception $e) {
            $error = "No se pudo limpiar la base de datos: " . $e->getMessage();
        }

        // Mostrar vista vacía
        try {
            $matches = $this->model->getAllMatches();
        } catch (Exception $e) {
            if (!$error)
                $error = $e->getMessage();
        }

        require __DIR__ . '/../../views/home.php';
    }
}
