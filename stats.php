<?php
/**
 * Generate HTML output for one item
 *
 * This is automatically called by tracker.php, or you can generate HTML output pages manually via:
 *
 * php stats.php 131215 > web/brexit.html
 */

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Europe/London');

require_once 'vendor/autoload.php';
require_once 'petitions.php';

$adapter = new Local(__DIR__);
$filesystem = new Filesystem($adapter);

if (!isset($petitionId) || !is_numeric($petitionId)) {
    if ($argc == 2 && isset($argv[1])) {
        $petitionId = filter_var($argv[1], FILTER_SANITIZE_NUMBER_INT);
    }
    if (empty($petitionId)) {
        throw new Exception('$petitionId must be set');
    }
}

$signatureCountFile = 'signature_count.' . $petitionId . '.txt';
if ($filesystem->has($signatureCountFile)) {
    $signature_count = json_decode($filesystem->read($signatureCountFile));
    if ($signature_count === false) {
        throw new Exception('Cannot decode data at ' . $signatureCountFile);
    }
} else {
    throw new Exception('No stats found');
}

// Get title
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

        $title = $data->data->attributes->action;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

$link = 'https://petition.parliament.uk/petitions/' . $petitionId;

$report = [];
$count = null;
$difference = null;
foreach ($signature_count as $item) {
    if ($count === null) {
        $count = $item->signature_count;
        continue;
    }

    $difference = $item->signature_count - $count;
    $count = $item->signature_count;

    $report[] = [
        'date' => new DateTime($item->date),
        'increase' => $difference,
        'count' => $count
    ];
}

// Build chart data
$series = [];
$dayData = [];
$day = '';
foreach ($report as $item) {
    $itemDay = $item['date']->format('l');

    if ($itemDay !== $day) {
        if (!empty($dayData)) {
            $series[] = $dayData;
            $dayData = [];
        }
        $dayData = [
            'name' => $itemDay,
            'data' => []
        ];
        $day = $itemDay;
    }

    $dayData['data'][] = [
        "Date.parse('{$item['date']->format('r')}')",
        $item['increase']
    ];
}
if (!empty($dayData)) {
    $series[] = $dayData;
    $dayData = [];
}

// Prepare JSON
$series = json_encode($series, JSON_PRETTY_PRINT);
$series = str_replace(['"Date.parse(', '\')",'], ['Date.parse(', '\'),'], $series);


// Calc average voting increase in past 2hrs
$now = new DateTime();
$oneHourAgo = clone $now;
$oneHourAgo->sub(new DateInterval('PT1H'));
$averageTimeSpan = clone $now;
$averageTimeSpan->sub(new DateInterval('PT30M'));
$averageTimeSpanText = 'half hour';

$x = 0;
$average = 0;
$pastHour = 0;
$lastCount = 0;
$lastUpdate = '';
foreach ($report as $item) {
    if ($item['date'] >= $averageTimeSpan) {
        $average += $item['increase'];
        $x++;
    }
    if ($item['date'] >= $oneHourAgo) {
        $pastHour += $item['increase'];
    }
    $lastCount = $item['count'];
    $lastUpdate = $item['date'];
}
$average = floor($average / $x);

$timeToReach = function($target) use ($lastCount, $average) {
    $now = new DateTime();
    $timeToVote = ceil(($target - $lastCount) / $average) * 5;
    return $now->add(new DateInterval('PT' . $timeToVote . 'M'));
};

?>
<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Petition Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://code.jquery.com/jquery-3.0.0.min.js"   integrity="sha256-JmvOoLtYsmqlsWxa7mDSLMwa6dZ9rrIdtrrVYRnDRH0="   crossorigin="anonymous"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>

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
<h2><a href="<?= $link ?>"><?= $title ?></a></h2>

<dl>
    <dt>Current count on <?php echo $item['date']->format('D jS M') ?> at <?php echo $item['date']->format('g:ia') ?></dt>
    <dd><?= number_format($lastCount) ?></dd>
    <dt>Average signatures every 5 minutes (over past <?= $averageTimeSpanText ?>)</dt>
    <dd> <?= number_format($average) ?></dd>
    <dt>Signatures in past hour</dt>
    <dd><?= number_format($pastHour) ?></dd>
    <?php if ($lastCount < 100000): ?>
    <dt>Estimated time to 100,000 signatures</dt>
    <dd><?php echo $timeToReach(100000)->format('r') ?></dd>
    <?php endif ?>
    <?php
    $nextMillion = floor($lastCount/1000000)+1;
    ?>
    <dt>Estimated time to <?= $nextMillion ?> million signatures</dt>
    <dd><?php echo $timeToReach($nextMillion * 1000000)->format('r') ?></dd>
    <dd class="small">Based on current volume of signatures</dd>
</dl>

<div id="container" style="min-width: 310px; height: 400px; margin: 0 auto"></div>

<script>
    $(function () {
        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });
        $('#container').highcharts({
            chart: {
                type: 'spline'
            },
            title: {
                text: 'Volume of signatures'
            },
            subtitle: {
                text: 'Every 5 minutes'
            },
            xAxis: {
                type: 'datetime',
                dateTimeLabelFormats: {
                    hour:"%A, %b %e, %H:%M"
                },
                title: {
                    text: 'Date'
                }
            },
            yAxis: {
                title: {
                    text: 'Signatures'
                },
                min: 0
            },
            tooltip: {
                headerFormat: '<b>{series.title} {point.x:%H:%M}</b><br>',
                pointFormat: '{point.y}'
            },

            plotOptions: {
                spline: {
                    marker: {
                        enabled: true
                    }
                }
            },

            series: <?= $series ?>
        });
    });
</script>

<p class="small"><a href="/">View all tracked petitions</a></p>

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




