<?php

namespace Pherring\Nmea2geojson;

use PHPUnit\Framework\TestCase;

final class NmeaTest extends TestCase
{
    public function testMissingDollarReturnsEmptyChecksum()
    {
        $result = Nmea::checksum('GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*');
        $this->assertSame('', $result);
    }
    public function testCalcValidChecksumWithMissingChecksum()
    {
        $result = Nmea::checksum('$GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*');
        $this->assertSame('67', $result);
    }
    public function testCalcValidChecksumWithWrongChecksum()
    {
        $result = Nmea::checksum('$GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*58');
        $this->assertSame('67', $result);
    }
 
    public function testVerifyMissingDollarReturnsNegative()
    {
        $result = Nmea::verify('GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*');
        $this->assertSame(-1, $result);
    }
    public function testVerifyMissingChecksumReturnsNegative()
    {
        $result = Nmea::verify('$GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*');
        $this->assertSame(-1, $result);
    }
    public function testVerifyEmptyChecksumReturnsNegative()
    {
        $result = Nmea::verify('$GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0');
        $this->assertSame(-1, $result);
    }
    public function testVerifyWrongChecksumReturnsZero()
    {
        $result = Nmea::verify('$GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*58');
        $this->assertSame(0, $result);
    }
    public function testVerifyCorrectChecksumReturnsPositive()
    {
        $result = Nmea::verify('$GPGSV,3,2,09,75,47,206,50,83,00,157,,84,55,132,33,85,52,002,53,0*67');
        $this->assertSame(1, $result);
    }

    public function testLatLngNorthWholeDegrees()
    {
        $result = Nmea::nmeaLatLng("2300.00", "N");
        $this->assertEqualsWithDelta(23.0, $result, 0.001);
    }
    public function testLatLngNorthHalfDegree()
    {
        $result = Nmea::nmeaLatLng("2330.00", "N");
        $this->assertEqualsWithDelta(23.5, $result, 0.001);
    }
    public function testLatLngNorthQuarterDegree()
    {
        $result = Nmea::nmeaLatLng("2315.00", "N");
        $this->assertEqualsWithDelta(23.25, $result, 0.001);
    }
    public function testLatLngSouth()
    {
        $result = Nmea::nmeaLatLng("2315.00", "S");
        $this->assertEqualsWithDelta(-23.25, $result, 0.001);
    }
    public function testLatLngEast()
    {
        $result = Nmea::nmeaLatLng("2315.00", "E");
        $this->assertEqualsWithDelta(23.25, $result, 0.001);
    }
    public function testLatLngWest()
    {
        $result = Nmea::nmeaLatLng("2315.00", "W");
        $this->assertEqualsWithDelta(-23.25, $result, 0.001);
    }

    public function testParseBadStringReturnsFail()
    {
        $result = Nmea::parse('$GPRMC,220516,A,5133.82,N,00042.24,W,173.8,231.8,130694,004.2,W*69'); // bad csum
        $this->assertSame([], $result);
    }

    public function testParseRMC()
    {
        $result = Nmea::parse('$GPRMC,220516,A,5133.82,N,00042.24,W,173.8,231.8,130694,004.2,W*70');
        $this->assertNotSame([], $result);

        $this->assertSame("GP", $result['talkerId']);
        $this->assertSame("RMC", $result['sentence']);

        $this->assertSame("220516", $result['parsed']['timestamp']);
        $this->assertSame("A", $result['parsed']['valid']);

        $this->assertEqualsWithDelta(51.5636667, $result['parsed']['latitude'], 0.000001);
        $this->assertEqualsWithDelta(-0.704, $result['parsed']['longitude'], 0.000001);
        $this->assertEqualsWithDelta(-0.704, $result['parsed']['x'], 0.000001);
        $this->assertEqualsWithDelta(51.5636667, $result['parsed']['y'], 0.000001);

        $this->assertEqualsWithDelta(173.8, $result['parsed']['knots'], 0.000001);
        $this->assertEqualsWithDelta(231.8, $result['parsed']['true'], 0.000001);

        $this->assertSame("130694", $result['parsed']['date']);

        $this->assertEqualsWithDelta(-0.07, $result['parsed']['variation'], 0.000001);
    }

    public function testParseVTG()
    {
        $result = Nmea::Parse('$GPVTG,360.0,T,348.7,M,000.0,N,000.0,K*43');
        $this->assertSame("GP", $result['talkerId']);
        $this->assertSame("VTG", $result['sentence']);
        $this->assertEqualsWithDelta(0, $result['parsed']['knots'], 0.000001);
    }

    public function testParseGGA()
    {
        $result = Nmea::Parse('$GPGGA,102918.00,3749.75922,S,14514.70408,E,2,12,0.54,137.9,M,-1.4,M,,0000*5B');
        $this->assertSame("GP", $result['talkerId']);
        $this->assertSame("GGA", $result['sentence']);
        $this->assertEqualsWithDelta(145.245068, $result['parsed']['x'], 0.000001);
        $this->assertEqualsWithDelta(-37.8293203, $result['parsed']['y'], 0.000001);
    }

    public function testParseGLL(){
        $result = Nmea::Parse('$GPGLL,5133.81,N,00042.25,W*75');
        $this->assertSame("GP", $result['talkerId']);
        $this->assertSame("GLL", $result['sentence']);
        $this->assertEqualsWithDelta(-0.704166667, $result['parsed']['x'], 0.000001);
        $this->assertEqualsWithDelta(51.5635, $result['parsed']['y'], 0.000001);

    }
}