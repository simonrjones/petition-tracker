<?php
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/London');

require 'vendor/autoload.php';

$adapter = new Local(__DIR__);
$filesystem = new Filesystem($adapter);

if ($filesystem->has('signature_count.txt')) {
    $signature_count = json_decode($filesystem->read('signature_count.txt'));
    if ($signature_count === false) {
        throw new Exception('Cannot decode data at signature_count.txt');
    }
} else {
    $signature_count = [];
}

try {
    $client = new GuzzleHttp\Client();
    $res = $client->request('GET', 'https://petition.parliament.uk/petitions/171928.json', [
        'headers' => [
            'User-Agent' => 'Petition-Tracker/1.0 (simon@studio24.net)'
        ]
    ]);

    if ($res->getStatusCode() == 200) {

        $data = json_decode($res->getBody());
        $now = new DateTime();

        $signature_count[] = [
            'date'            => $now->format('r'),
            'signature_count' => $data->data->attributes->signature_count
        ];

        $filesystem->put('signature_count.txt', json_encode($signature_count, JSON_PRETTY_PRINT));

        echo "Count: " . $data->data->attributes->signature_count . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

// Generate page
ob_start();
require 'stats.php';
$contents = ob_get_clean();
$filesystem->put('web/index.html', $contents);

echo 'All done!' . PHP_EOL;

