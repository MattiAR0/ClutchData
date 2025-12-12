<?php
require __DIR__ . '/vendor/autoload.php';
use App\Models\MatchModel;

$model = new MatchModel();

echo "Testing ALL matches:\n";
$all = $model->getAllMatches(null, 'all', 'all');
echo "Count: " . count($all) . "\n";

echo "\nTesting UPCOMING matches:\n";
$upcoming = $model->getAllMatches(null, 'all', 'upcoming');
echo "Count: " . count($upcoming) . "\n";
foreach ($upcoming as $m) {
    if ($m['match_status'] !== 'upcoming')
        echo "FAIL: Found " . $m['match_status'] . "\n";
}

echo "\nTesting COMPLETED matches:\n";
$completed = $model->getAllMatches(null, 'all', 'completed');
echo "Count: " . count($completed) . "\n";
foreach ($completed as $m) {
    if ($m['match_status'] === 'upcoming')
        echo "FAIL: Found upcoming in completed\n";
}
