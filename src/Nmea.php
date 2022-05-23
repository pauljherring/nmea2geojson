<?php

declare(strict_types=1);

namespace Pherring\Nmea2geojson;

class Nmea
{
    /**
     * Take an NMEA string, make sure it starts with a `$`, ignore optional 
     * `*<checksum>` and return the checksum as it should be
     * @param string $nmea
     * @return string 2 digit hex checksum if valid NMEA, empty string otherwise
     */
    public static function checksum($nmea)
    {
        $chars = str_split($nmea);
        if ($chars[0] != '$') {
            Log::i("Not an NMEA string: $nmea");
            return '';
        }
        array_shift($chars); // remove $

        $checksum = 0;
        foreach($chars as $c) {
            if ( $c == '*' ) {
                break; // complete
            }
            $checksum ^= ord($c);
        }
        return sprintf('%02X', $checksum);
    }

    /**
     * Verify a NMEA string, with a checksum, has the correct checksum
     * @param string $nmea
     * @return int -1 if no checksum present or not nmea string, 0 if bad, 1 if good
     */
    public static function verify($nmea)
    {
        $pattern = '/[\$*]/';
        $parts = preg_split($pattern, $nmea);
        if ( count($parts) != 3) {
            Log::i("Missing/extra bits: $nmea");
            return -1;
        }
        if ($parts[2] == '') {
            Log::i("Missing checksum: $nmea");
            return -1;
        }

        if ( ($checksum = self::checksum($nmea)) == '') {
            Log::i("Unable to calc checksum: $nmea");
            return -1;
        }
        if (!(strcasecmp($parts[2], $checksum))) {
            return 1;
        }
        return 0;
    }

    /**
     * convert {D}DDmm.mmmmm N/S/E/W to {d}dd.dddd
     * @param string $value {DD}Dmm.mmmm
     * @param string $direction N/S/E/W
     * @return float
     */
    public static function nmeaLatLng($value, $direction)
    {
        $int_degrees = intval(floatval($value)/100.0);
        $minutes2deg = floatval((floatval($value) - $int_degrees*100))/60.0;
        $degrees = floatval($int_degrees + $minutes2deg);

        if (!strcasecmp($direction, "s") || !strcasecmp($direction, "W")) {
            $degrees *= -1;
        }
        return $degrees;
    }

    // /** 
    //  * convert ddmmtt to UTC seconds
    //  * @param string $date
    //  * @return int
    //  */
    // public static function ddmmyy($date)
    // {
    //     $t = intval($date);
    //     $y = ($t % 100) + 2000; // presume date after 2000
    //     $t = intval($t/100);
    //     $m = ($t % 100);
    //     $t = intval($t/100);
    //     $d = $t;
    //     return strtotime("%y/%m/%d");
    // }


    public static function rmc($bits) 
    {
        // eg3. $GPRMC,220516,A,5133.82,N,00042.24,W,173.8,231.8,130694,004.2,W*70
        //           1    2    3    4    5     6    7    8      9     10  11 12


        //   1   220516     Time Stamp
        //   2   A          validity - A-ok, V-invalid
        //   3   5133.82    current Latitude
        //   4   N          North/South
        //   5   00042.24   current Longitude
        //   6   W          East/West
        //   7   173.8      Speed in knots
        //   8   231.8      True course
        //   9   130694     Date Stamp
        //   10  004.2      Variation
        //   11  W          East/West
        //   12  *70        checksum
        $data['timestamp'] = $bits[0];
        // lat = y, lng = x; see e.g. https://gis.stackexchange.com/a/68856
        $data['valid'] = $bits[1];
        $data['y'] = $data['latitude'] = self::nmeaLatLng($bits[2], $bits[3]);
        $data['x'] = $data['longitude'] = self::nmeaLatLng($bits[4], $bits[5]);
        $data['knots'] = floatval($bits[6]);
        $data['true'] = floatval($bits[7]);
        $data['date'] = $bits[8];
        $data['variation'] = self::nmeaLatLng($bits[9], $bits[10]);
        
        return $data;
    }

    static function vtg($bits) 
    {
        // eg1. $GPVTG,360.0,T,348.7,M,000.0,N,000.0,K*43
        //             1     2 3     4 5     6 7     8 
        // eg2. $GPVTG,054.7,T,034.4,M,005.5,N,010.2,K
        
        
        //            054.7,T      True track made good
        //            034.4,M      Magnetic track made good
        //            005.5,N      Ground speed, knots
        //            010.2,K      Ground speed, Kilometers per hour
        
        
        // eg3. $GPVTG,t,T,,,s.ss,N,s.ss,K*hh
        // 1    = Track made good
        // 2    = Fixed text 'T' indicates that track made good is relative to true north
        // 3    = not used
        // 4    = not used
        // 5    = Speed over ground in knots
        // 6    = Fixed text 'N' indicates that speed over ground in in knots
        // 7    = Speed over ground in kilometers/hour
        // 8    = Fixed text 'K' indicates that speed over ground is in kilometers/hour        
        $data['knots'] = floatval($bits[4]);
        $data['kmh'] = floatval($bits[6]);


        return $data;
    }
    static function gga($bits) 
    {
        // $GPGGA,102918.00,3749.75922,S,14514.70408,E,2,12,0.54,137.9,M,-1.4,M,,0000*5B
        //        1         2          3 4           5 6 7  8    9     A B    C D E
        // eg3. $GPGGA,hhmmss.ss,llll.ll,a,yyyyy.yy,a,x,xx,x.x,x.x,M,x.x,M,x.x,xxxx*hh
        // 1    = UTC of Position
        // 2    = Latitude
        // 3    = N or S
        // 4    = Longitude
        // 5    = E or W
        // 6    = GPS quality indicator (0=invalid; 1=GPS fix; 2=Diff. GPS fix)
        // 7    = Number of satellites in use [not those in view]
        // 8    = Horizontal dilution of position
        // 9    = Antenna altitude above/below mean sea level (geoid)
        // 10   = Meters  (Antenna height unit)
        // 11   = Geoidal separation (Diff. between WGS-84 earth ellipsoid and
        //        mean sea level.  -=geoid is below WGS-84 ellipsoid)
        // 12   = Meters  (Units of geoidal separation)
        // 13   = Age in seconds since last update from diff. reference station
        // 14   = Diff. reference station ID#
        // 15   = Checksum
        $data['y'] = $data['latitude'] = self::nmeaLatLng($bits[1], $bits[2]);
        $data['x'] = $data['longitude'] = self::nmeaLatLng($bits[3], $bits[4]);


        return $data;
    }
    static function gll($bits) 
    {
        // eg3. $GPGLL,5133.81,N,00042.25,W*75
        // 1    2     3    4 5

        // 1    5133.81   Current latitude
        // 2    N         North/South
        // 3    00042.25  Current longitude
        // 4    W         East/West
        // 5    *75       checksum

        $data['y'] = $data['latitude'] = self::nmeaLatLng($bits[0], $bits[1]);
        $data['x'] = $data['longitude'] = self::nmeaLatLng($bits[2], $bits[3]);

        return $data;
    }

    static function txt($bits)
    {
        return $bits;
    }
    static function gsv($bits)
    {
        return $bits;
    }
    static function gsa($bits)
    {
        return $bits;
    }


    /**
     * Parse an NMEA string and (if valid) return data in an array
     * @param string $nmea
     * @return array {
     *  talkerId?: string,
     *  sentence?: string,
     *  data?: array{}
     * }
     */
    public static function parse($nmea)
    {
        $res = [];
        if (self::verify(trim($nmea)) != 1) {
            return $res; // bad string
        }
        $pattern = '/[\$*,]/';
        $bits = preg_split($pattern, $nmea);
        array_shift($bits); // remove first empty match which was the $
        array_pop($bits); // remove checksum
        $res['talkerId'] = substr($bits[0], 0, 2);
        $res['sentence'] = substr($bits[0], 2);
        array_shift($bits); // remove talkerId/sentence
        $res['raw'] = $bits;
        $function = "self::{$res['sentence']}";
        if (is_callable($function)) {
            $res['parsed'] = self::{$res['sentence']}($bits);
        } else {
            Log::i("Unhandled sentence: {$res['sentence']}");
        }
        return $res;
    }

}