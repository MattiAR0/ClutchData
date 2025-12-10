<?php

namespace App\Interfaces;

interface ScraperInterface
{
    /**
     * Extrae los partidos de la fuente.
     *
     * @return array Lista de partidos encontrados
     */
    public function scrapeMatches(): array;
}
