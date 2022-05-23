#!/usr/bin/php
<?php

declare(strict_types=1);

namespace Pherring\Nmea2geojson;

$loader = require __DIR__ . '/../vendor/autoload.php'; // loads the Composer autoloader

// $properties = [ "id" => 2];

// $coordinates[] = [145, -37];
// $coordinates[] = [146, -38];

// $geometry = [
//     "type" => "MultiLineString",
//     "coordinates" => $coordinates,
// ];

// $document = [
//     "type" => "Feature",
//     "properties" => $properties,
//     "geometry" => $geometry,
// ];


// echo json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

// {
//     "type": "Feature",
//     "properties": {
//         "id": 2
//     },
//     "geometry": {
//         "type": "MultiLineString",
//         "coordinates": [
//             [
//                 145,
//                 -37
//             ],
//             [
//                 146,
//                 -38
//             ]
//         ]
//     }
// }


// exit();

// Script example.php
$options = getopt("f:h");
if ($file = $options['f']??null) {
    if (!file_exists($file)) {
        echo "$file not found\n";
        exit();
    }

    $coordinates = [];
    $count = 0;
    $handle = fopen($file, "r");
    if ($handle) {
        while ((($line = fgets($handle)) !== false)) {
            if (!(($count +=1) %100)) {
                fwrite(STDERR, ".");
            } ;
            // echo $line;
            $r = Nmea::parse($line);
            if (array_key_exists('parsed', $r)) {
                if (array_key_exists("x", $r['parsed'])) {
                    $coordinates[] = [$r['parsed']['x'], $r['parsed']['y']];
                }
            }
        }
        fclose($handle);
    }

    $properties = [ "id" => 2];
    $geometry = [
        "type" => "MultiLineString",
        "coordinates" => [$coordinates],
    ];

    $document = [
        "type" => "Feature",
        "properties" => $properties,
        "geometry" => $geometry,
    ];


    echo json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
}