<?php
require 'vendor/autoload.php';

use App\Models\MatchModel;

try {
    $model = new MatchModel();
    echo "Attempting to delete all matches...\n";
    $model->deleteAllMatches();
    echo "Success! All matches deleted.\n";

    // Verify count is 0
    $count = $model->getMatchCount();
    echo "Match count: $count\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
