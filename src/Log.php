<?php

declare(strict_types=1);

namespace Pherring\Nmea2geojson;

class Log
{
    static function i($s)
    {
        echo $s."\n";
    }
}
