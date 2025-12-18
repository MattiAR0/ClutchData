<?php
$html = file_get_contents('debug_html.html');
echo "Tamaño: " . strlen($html) . " bytes\n";
echo "match-info: " . substr_count($html, 'match-info') . "\n";
echo "match-info-header-opponent: " . substr_count($html, 'match-info-header-opponent') . "\n";
echo "timer-object: " . substr_count($html, 'timer-object') . "\n";
echo "match-row: " . substr_count($html, 'match-row') . "\n";
echo "wikitable: " . substr_count($html, 'wikitable') . "\n";
echo "infobox: " . substr_count($html, 'infobox') . "\n";
