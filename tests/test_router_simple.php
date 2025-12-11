<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\Router;

// Mock environment for testing
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';

echo "Testing Router...\n";

try {
    $router = new Router();
    $router->get('/', function () {
        echo "Route / matched successfully!\n";
    });

    // Capture output to verify
    ob_start();
    $router->resolve();
    $output = ob_get_clean();

    if (trim($output) === "Route / matched successfully!") {
        echo "PASS: Router basic resolution works.\n";
    } else {
        echo "FAIL: Router did not match /. Output: '$output'\n";
    }

} catch (Exception $e) {
    echo "FAIL: Exception thrown: " . $e->getMessage() . "\n";
}
