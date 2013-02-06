<?php

/**
 * This file is part of the Geotools library.
 *
 * (c) Antoine Corcy <contact@sbin.dk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Geotools\Distance;

use Geotools\AbstractGeotools;
use Geotools\Coordinate\CoordinateInterface;

/**
* @author Antoine Corcy <contact@sbin.dk>
*/
class Distance extends AbstractGeotools implements DistanceInterface
{
    /**
     * The user unit.
     *
     * @var string
     */
    protected $unit;


    /**
     * {@inheritDoc}
     */
    public function setFrom(CoordinateInterface $from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * {@inheritDoc}
     */
    public function setTo(CoordinateInterface $to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * {@inheritDoc}
     */
    public function in($unit)
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Returns the approximate flat distance between two cordinates
     * using Pythagoras’ theorem which is not very accurate.
     * @see http://en.wikipedia.org/wiki/Pythagorean_theorem
     * @see http://en.wikipedia.org/wiki/Equirectangular_projection
     *
     * @return double The distance in meters
     */
    public function flat()
    {
        $latA = deg2rad($this->from->getLatitude());
        $lngA = deg2rad($this->from->getLongitude());
        $latB = deg2rad($this->to->getLatitude());
        $lngB = deg2rad($this->to->getLongitude());

        $x = ($lngB - $lngA) * cos(($latA + $latB) / 2);
        $y = $latB - $latA;

        $d = sqrt(($x * $x) + ($y * $y)) * AbstractGeotools::EARTH_RADIUS;

        return $this->convertToUserUnit($d);
    }

    /**
    * Returns the approximate sea level great circle (Earth) distance between
    * two cordinates using the Haversine formula which is accurate to around 0.3%.
    * @see http://www.movable-type.co.uk/scripts/latlong.html
    *
    * @return double The distance in meters
    */
    public function haversine()
    {
        $latA = deg2rad($this->from->getLatitude());
        $lngA = deg2rad($this->from->getLongitude());
        $latB = deg2rad($this->to->getLatitude());
        $lngB = deg2rad($this->to->getLongitude());

        $dLat = $latB - $latA;
        $dLon = $lngB - $lngA;

        $a = sin($dLat / 2) * sin($dLat / 2) + cos($latA) * cos($latB) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $this->convertToUserUnit(AbstractGeotools::EARTH_RADIUS * $c);
    }

    /**
    * Returns geodetic distance between between two cordinates using Vincenty inverse
    * formula for ellipsoids which is accurate to within 0.5mm.
    * @see http://www.movable-type.co.uk/scripts/latlong-vincenty.html
    *
    * @return double The distance in meters
    */
    public function vincenty()
    {
        // WGS-84 ellipsoid params
        $a = AbstractGeotools::EARTH_RADIUS;
        $b = 6356752.314245;
        $f = 1/298.257223563;

        $L  = deg2rad($this->to->getLongitude() - $this->from->getLongitude());
        $U1 = atan((1 - $f) * tan(deg2rad($this->from->getLatitude())));
        $U2 = atan((1 - $f) * tan(deg2rad($this->to->getLatitude())));

        $sinU1 = sin($U1);
        $cosU1 = cos($U1);
        $sinU2 = sin($U2);
        $cosU2 = cos($U2);

        $lambda = $L;
        $lambdaP = 2 * pi();
        $iterLimit = 100;

        do {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            $sinSigma  = sqrt(($cosU2 * $sinLambda) * ($cosU2 * $sinLambda) +
                         ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) *
                         ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda));

            if ($sinSigma == 0) {
              return 0; // co-incident points
            }

            $cosSigma   = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma      = atan2($sinSigma, $cosSigma);
            $sinAlpha   = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
            $cosSqAlpha = 1 - $sinAlpha * $sinAlpha;
            $cos2SigmaM = $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha;

            $C       = $f / 16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));
            $lambdaP = $lambda;
            $lambda  = $L + (1 - $C) * $f * $sinAlpha *  ($sigma + $C * $sinSigma * ($cos2SigmaM +
                       $C * $cosSigma * (-1 + 2 * $cos2SigmaM * $cos2SigmaM)));
        } while (abs($lambda - $lambdaP) > 1e-12 && --$iterLimit > 0);

        if ($iterLimit == 0) {
            return null; // formula failed to converge
        }

        $uSq        = $cosSqAlpha * ($a * $a - $b * $b) / ($b * $b);
        $A          = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B          = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));
        $deltaSigma = $B * $sinSigma * ($cos2SigmaM + $B / 4 * ($cosSigma * (-1 + 2 * $cos2SigmaM *
                      $cos2SigmaM) - $B / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma * $sinSigma) * (-3 +
                      4 * $cos2SigmaM * $cos2SigmaM)));
        $s          = $b * $A * ($sigma - $deltaSigma);

        return $this->convertToUserUnit($s);
    }

    /**
     * Converts results in meters to user's unit (if any).
     * The default returned value is in meters.
     * @param  double $meters
     *
     * @return double
     */
    protected function convertToUserUnit($meters)
    {
        switch ($this->unit) {
            case AbstractGeotools::KILOMETER_UNIT:
                return $meters / 1000;
            case AbstractGeotools::MILE_UNIT:
                return $meters * AbstractGeotools::METERS_PER_MILE;
            default:
                return $meters;
        }
    }
}
