<?php
$html = file_get_contents('c:/wamp64/www/ClutchData/debug_html.html');
$pos = strpos($html, 'data-toggle-area-content="2"');
if ($pos !== false) {
    echo substr($html, $pos, 3000);
} else {
    echo "String not found";
}
