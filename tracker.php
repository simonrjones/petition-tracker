<?php
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/London');

require_once 'vendor/autoload.php';
require_once 'petitions.php';

$adapter = new Local(__DIR__);
$filesystem = new Filesystem($adapter);

function trackPetition($petitionId, $htmlOutput)
{
    global $filesystem;

    $signatureCountFile = 'signature_count.' . $petitionId . '.txt';
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
                'User-Agent' => 'Petition-Tracker/1.0.1 (simon@studio24.net)'
            ]
        ]);

        if ($res->getStatusCode() == 200) {

            $data = json_decode($res->getBody());
            $now = new DateTime();

            $state = $data->data->attributes->state;

            if ($state == 'open') {
                $signature_count[] = [
                    'title'           => $data->data->attributes->action,
                    'date'            => $now->format('r'),
                    'signature_count' => $data->data->attributes->signature_count
                ];
                $filesystem->put($signatureCountFile, json_encode($signature_count, JSON_PRETTY_PRINT));
            }

            echo "Count for petition ID $petitionId: " . $data->data->attributes->signature_count . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }

    // Generate stats page
    if ($state == 'open') {
        ob_start();
        require 'stats.php';
        $contents = ob_get_clean();
        $filesystem->put('web/' . $htmlOutput, $contents);
    }

    // Return petition title
    return ['title' => $data->data->attributes->action, 'state' => $state];
}

// Track petitions
$open = [];
$closed = [];
foreach ($petitions as $petitionId => $htmlOutput) {
    $data = trackPetition($petitionId, $htmlOutput);
    $title = $data['title'];
    if ($data['state'] == 'open') {
        $open[] = '<a href="' . $htmlOutput . '">' . $title . '</a>';
    } else {
        $closed[] = '<a href="' . $htmlOutput . '">' . $title . '</a>';
    }
}


// Generate index page
$content = <<<EOD
<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Petition Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 1.4em;
            line-height: 1.4em;
            margin: 0;
            padding: 1em;
        }
        h1 {
            font-size: 1.6em;
            margin: 0;
        }
        h2 {
            font-size: 1.2em;
            font-weight: normal;
            margin-top: 0.4em;
        }
        dt {
            font-weight: bold;
            margin-top: 0.8em;
        }
        dd {
            font-weight: normal;
            margin-left: 0;
        }
        .small {
            font-size: 0.75em;
            font-style: italic;
        }
    </style>
</head>
<body>

<h1>Petition tracker</h1>

<p>Currently tracking...</p>
<ul>

EOD;

foreach ($open as $link) {
    $content .= "<li>$link</li>";
}

$content .= <<<EOD
</ul>
<p>Closed petitions...</p>
<ul>
EOD;

foreach ($closed as $link) {
    $content .= "<li>$link</li>";
}

$content .= <<<EOD
</ul>
<p class="small">Petition tracker by <a href="https://twitter.com/simonrjones">Simon R Jones</a> / <a href="http://www.studio24.net/">Studio 24</a></p>

<script>
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

    ga('create', 'UA-10517635-2', 'auto');
    ga('send', 'pageview');

</script>

</body>
</html>
EOD;

$filesystem->put('web/index.html', $content);

echo 'All done!' . PHP_EOL;

