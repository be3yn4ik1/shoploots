<?php
// Одноразовый скрипт — удалите после использования
$public  = '51cd7e1b02c85e5439dffbf9267b4f57';
$private = 'bq6gDYg2mbHZDbIKXtljtF6pGHTCINsfdbrfdvy75qXYweueGc';
$sign    = hash('sha256', $private);

$urls = [
    'payment_systems' => "https://api.fkwallet.io/v1/{$public}/payment_systems",
    'currencies'      => "https://api.fkwallet.io/v1/{$public}/currencies",
];

foreach ($urls as $label => $url) {
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "Authorization: Bearer {$sign}\r\nContent-Type: application/json\r\n",
        'timeout' => 15,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    echo "<h2>{$label}</h2><pre>" . htmlspecialchars(json_encode(json_decode($resp), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
}
