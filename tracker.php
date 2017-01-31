<?php
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/London');

require 'vendor/autoload.php';

// Array of petition IDs => HTML page to create
$petitions = [
    171928 => 'index.html',
    178844 => 'trump.html'
];

$adapter = new Local(__DIR__);
$filesystem = new Filesystem($adapter);

function trackPetition($petitionId, $htmlOutput)
{
    $version = '1.0.1';

    $signatureCountFile = 'signature_count_' . $petitionId . '.txt';
    if ($filesystem->has($signatureCountFile)) {
        $signature_count = json_decode($filesystem->read($signatureCountFile));
        if ($signature_count === false) {
            throw new Exception('Cannot decode data at ' . $signatureCountFile);
        }
    } else {
        $signature_count = [];
    }

    try {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', 'https://petition.parliament.uk/petitions/' . $petitionId . '.json', [
            'headers' => [
                'User-Agent' => 'Petition-Tracker/' . $version . ' (simon@studio24.net)'
            ]
        ]);

        if ($res->getStatusCode() == 200) {

            $data = json_decode($res->getBody());
            $now = new DateTime();

            $signature_count[] = [
                'date'            => $now->format('r'),
                'signature_count' => $data->data->attributes->signature_count
            ];

            $filesystem->put($signatureCountFile, json_encode($signature_count, JSON_PRETTY_PRINT));

            echo "Count for petition ID $petitionId: " . $data->data->attributes->signature_count . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }

    // Generate stats page
    ob_start();
    require 'stats.php';
    $contents = ob_get_clean();
    $filesystem->put('web/' . $htmlOutput, $contents);
}

echo 'All done!' . PHP_EOL;

