<?php
namespace geoPHP\Geometry;

use geoPHP\geoPHP;

/**
 * A LineString is defined by a sequence of points, (X,Y) pairs, which define the reference points of the line string.
 * Linear interpolation between the reference points defines the resulting linestring.
 *
 * @method   Point[] getComponents()
 * @property Point[] $components
 */
class LineString extends Curve
{

    /**
     * @return string "LineString"
     */
    public function geometryType(): string
    {
        return Geometry::LINESTRING;
    }

    /**
     * @param  array<array> $array
     * @return LineString
     */
    public static function fromArray(array $array): LineString
    {
        $points = [];
        foreach ($array as $point) {
            $points[] = Point::fromArray($point);
        }
        return new LineString($points);
    }

    /**
     * Returns the number of points of the LineString
     *
     * @return int
     */
    public function numPoints(): int
    {
        return count($this->components);
    }

    /**
     * Returns the 1-based Nth point of the LineString.
     * Negative values are counted backwards from the end of the LineString.
     *
     * @param  int $n Nth point of the LineString
     * @return Point|null
     */
    public function pointN(int $n)
    {
        $n = $n < $this->numPoints() ? $n : $this->numPoints();
        
        /** @var Point|null $point */
        $point = $n >= 0
                ? $this->geometryN($n)
                : $this->geometryN(count($this->components) - abs($n + 1));
        
        return $point;
    }

    /**
     * @return Point
     */
    public function getCentroid(): Point
    {
        if ($this->isEmpty()) {
            return new Point();
        }

        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            /** @phpstan-ignore-next-line */
            return geoPHP::geosToGeometry($geosObj->centroid());
            // @codeCoverageIgnoreEnd
        }

        $x = 0;
        $y = 0;
        $length = 0.0;
        $points = $this->getPoints();
        $numPoints = count($points)-1;
        for ($i=0; $i<$numPoints; ++$i) {
            $currX = $points[$i]->getX();
            $currY = $points[$i]->getY();
            $nextX = $points[$i+1]->getX();
            $nextY = $points[$i+1]->getY();
            
            $dx = $nextX - $currX;
            $dy = $nextY - $currY;
            $segmentLength = sqrt($dx*$dx + $dy*$dy);
            $length += $segmentLength;
            $x += ($currX + $nextX) / 2 * $segmentLength;
            $y += ($currY + $nextY) / 2 * $segmentLength;
        }

        return $length === 0.0 ? $this->startPoint() : new Point($x / $length, $y / $length);
    }

    /**
     * Returns the length of this Curve in its associated spatial reference.
     * E.g. if Geometry is in geographical coordinate system it returns the length in degrees.
     *
     * @return float
     */
    public function getLength(): float
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->length();
            // @codeCoverageIgnoreEnd
        }
        
        $length = 0.0;
        $points = $this->getPoints();
        $numPoints = count($points)-1;
        for ($i=0; $i<$numPoints; ++$i) {
            $length += sqrt(
                pow(($points[$i]->getX() - $points[$i+1]->getX()), 2) +
                pow(($points[$i]->getY() - $points[$i+1]->getY()), 2)
            );
        }
        
        return $length;
    }

    /**
     * Returns the length of a 3-dimensional geometry.
     *
     * @return float
     */
    public function length3D(): float
    {
        $length = 0.0;
        
        $previousPoint = null;
        foreach ($this->getPoints() as $point) {
            if ($previousPoint) {
                $length += sqrt(
                    pow(($previousPoint->getX() - $point->getX()), 2) +
                    pow(($previousPoint->getY() - $point->getY()), 2) +
                    pow(($previousPoint->getZ() - $point->getZ()), 2)
                );
            }
            /** @var Point $previousPoint */
            $previousPoint = $point;
        }
        return $length;
    }

    /**
     * @param  float $radius Default is the semi-major axis of WGS84.
     * @return float length in meters
     */
    public function greatCircleLength(float $radius = geoPHP::EARTH_WGS84_SEMI_MAJOR_AXIS): float
    {
        $length = 0.0;
        $rad = M_PI / 180;
        $points = $this->getPoints();
        $numPoints = $this->numPoints() - 1;
        for ($i = 0; $i < $numPoints; ++$i) {
            // Simplified Vincenty formula with equal major and minor axes (a sphere)
            $lat1 = $points[$i]->getY() * $rad;
            $lat2 = $points[$i + 1]->getY() * $rad;
            $lon1 = $points[$i]->getX() * $rad;
            $lon2 = $points[$i + 1]->getX() * $rad;
            $deltaLon = $lon2 - $lon1;
            $d = $radius *
                atan2(
                    sqrt(
                        pow(cos($lat2) * sin($deltaLon), 2) +
                        pow(cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLon), 2)
                    ),
                    sin($lat1) * sin($lat2) +
                    cos($lat1) * cos($lat2) * cos($deltaLon)
                );
            if ($points[$i]->is3D()) {
                $d = sqrt(
                    pow($d, 2) +
                    pow($points[$i + 1]->getZ() - $points[$i]->getZ(), 2)
                );
            }

            $length += $d;
        }
        
        return $length;
    }

    /**
     * @return float Haversine length of geometry in degrees
     */
    public function haversineLength(): float
    {
        $distance = 0.0;
        $points = $this->getPoints();
        $numPoints = $this->numPoints() - 1;
        for ($i = 0; $i < $numPoints; ++$i) {
            $point = $points[$i];
            $nextPoint = $points[$i + 1];
            $degree = (geoPHP::EARTH_WGS84_SEMI_MAJOR_AXIS *
                acos(
                    sin(deg2rad($point->getY())) * sin(deg2rad($nextPoint->getY())) +
                    cos(deg2rad($point->getY())) * cos(deg2rad($nextPoint->getY())) *
                    cos(deg2rad(abs($point->getX() - $nextPoint->getX())))
                )
            );
            if (!is_nan($degree)) {
                $distance += $degree;
            }
        }
        return $distance;
    }

    /**
     * @source  https://github.com/mjaschen/phpgeo/blob/master/src/Location/Distance/Vincenty.php
     * @author  Marcus Jaschen <mjaschen@gmail.com>
     * @license https://opensource.org/licenses/GPL-3.0 GPL
     * (note: geoPHP uses "GPL version 2 (or later)" license which is compatible with GPLv3)
     *
     * @return float Length in meters
     */
    public function vincentyLength(): float
    {
        $length = 0.0;
        $rad = M_PI / 180;
        $points = $this->getPoints();
        $numPoints = count($points) - 1;
        for ($i = 0; $i < $numPoints; ++$i) {
            // Inverse Vincenty formula
            $lat1 = $points[$i]->getY() * $rad;
            $lat2 = $points[$i + 1]->getY() * $rad;
            $lng1 = $points[$i]->getX() * $rad;
            $lng2 = $points[$i + 1]->getX() * $rad;

            $a = geoPHP::EARTH_WGS84_SEMI_MAJOR_AXIS;
            $b = geoPHP::EARTH_WGS84_SEMI_MINOR_AXIS;
            $f = 1 / geoPHP::EARTH_WGS84_FLATTENING;
            $L = $lng2 - $lng1;
            $U1 = atan((1 - $f) * tan($lat1));
            $U2 = atan((1 - $f) * tan($lat2));
            $iterationLimit = 100;
            $lambda = $L;
            $sinU1 = sin($U1);
            $sinU2 = sin($U2);
            $cosU1 = cos($U1);
            $cosU2 = cos($U2);
            do {
                $sinLambda = sin($lambda);
                $cosLambda = cos($lambda);
                $sinSigma = sqrt(
                    ($cosU2 * $sinLambda) *
                    ($cosU2 * $sinLambda) +
                    ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) *
                    ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda)
                );
                if ($sinSigma == 0) {
                    return 0.0;
                }
                $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
                $sigma = atan2($sinSigma, $cosSigma);
                $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
                $cosSqAlpha = 1 - $sinAlpha * $sinAlpha;
                $cos2SigmaM = 0;
                if ($cosSqAlpha <> 0) {
                    $cos2SigmaM = $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha;
                }
                $C = $f / 16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));
                $lambdaP = $lambda;
                $lambda = $L + (1 - $C) * $f * $sinAlpha *
                    ($sigma + $C * $sinSigma * ($cos2SigmaM + $C * $cosSigma * (- 1 + 2 * $cos2SigmaM * $cos2SigmaM)));
            } while (abs($lambda - $lambdaP) > 1e-12 && --$iterationLimit > 0);
            if ($iterationLimit == 0) {
                return 0.0; // not converging
            }
            $uSq = $cosSqAlpha * ($a * $a - $b * $b) / ($b * $b);
            $A = 1 + $uSq / 16384 * (4096 + $uSq * (- 768 + $uSq * (320 - 175 * $uSq)));
            $B = $uSq / 1024 * (256 + $uSq * (- 128 + $uSq * (74 - 47 * $uSq)));
            $deltaSigma = $B * $sinSigma * ($cos2SigmaM + $B / 4 *
                    ($cosSigma * (-1 + 2 * $cos2SigmaM * $cos2SigmaM) - $B / 6
                        * $cos2SigmaM * (-3 + 4 * $sinSigma * $sinSigma)
                        * (-3 + 4 * $cos2SigmaM * $cos2SigmaM)));

            $length += $b * $A * ($sigma - $deltaSigma);
        }
        // Returns length in meters.
        return $length;
    }

    /**
     * @return int|float|null
     */
    public function minimumZ()
    {
        $min = PHP_INT_MAX;
        foreach ($this->getPoints() as $point) {
            if (null !== ($z = $point->getZ()) && $z < $min) {
                $min = $z;
            }
        }
        return $min !== PHP_INT_MAX ? $min : null;
    }

    /**
     * @return int|float|null
     */
    public function maximumZ()
    {
        $max = PHP_INT_MIN;
        foreach ($this->getPoints() as $point) {
            if (null !== ($z = $point->getZ()) && $z > $max) {
                $max = $z;
            }
        }

        return $max !== PHP_INT_MIN ? $max : null;
    }

    /**
     * @return int|float|null
     */
    public function zDifference()
    {
        if ($this->startPoint()->hasZ() && $this->endPoint()->hasZ()) {
            return abs($this->startPoint()->getZ() - $this->endPoint()->getZ());
        }
        
        return null;
    }

    /**
     * Returns the cumulative elevation gain of the LineString
     *
     * @param int|float|null $verticalTolerance Smoothing factor filtering noisy elevation data.
     *                                          Its unit equals to the z-coordinates unit (meters for geographical coordinates)
     *                                          If the elevation data comes from a DEM, a value around 3.5 can be acceptable.
     *
     * @return float
     */
    public function elevationGain($verticalTolerance = 0)
    {
        $gain = 0.0;
        $lastEle = $this->startPoint()->getZ();
        $numPoints = $this->numPoints();
        
        foreach ($this->getPoints() as $i => $point) {
            if (abs($point->getZ() - $lastEle) > $verticalTolerance || $i === $numPoints - 1) {
                if ($point->getZ() > $lastEle) {
                    $gain += $point->getZ() - $lastEle;
                }
                $lastEle = $point->getZ();
            }
        }
        
        return $gain;
    }

    /**
     * Returns the cumulative elevation loss of the LineString
     *
     * @param int|float|null $verticalTolerance Smoothing factor filtering noisy elevation data.
     *                                          Its unit equals to the z-coordinates unit (meters for geographical coordinates)
     *                                          If the elevation data comes from a DEM, a value around 3.5 can be acceptable.
     *
     * @return float
     */
    public function elevationLoss($verticalTolerance = 0)
    {
        $loss = 0.0;
        $lastEle = $this->startPoint()->getZ();
        $numPoints = $this->numPoints();
        
        foreach ($this->getPoints() as $i => $point) {
            if (abs($point->getZ() - $lastEle) > $verticalTolerance || $i === $numPoints - 1) {
                if ($point->getZ() < $lastEle) {
                    $loss += $lastEle - $point->getZ();
                }
                $lastEle = $point->getZ();
            }
        }
        
        return $loss;
    }

    public function minimumM()
    {
        $min = PHP_INT_MAX;
        foreach ($this->getPoints() as $point) {
            if ($point->isMeasured() && $point->m() < $min) {
                $min = $point->m();
            }
        }
        return $min !== PHP_INT_MAX ? $min : null;
    }

    public function maximumM()
    {
        $max = PHP_INT_MIN;
        foreach ($this->getPoints() as $point) {
            if ($point->isMeasured() && $point->m() > $max) {
                $max = $point->m();
            }
        }

        return $max !== PHP_INT_MIN ? $max : null;
    }

    /**
     * Get all line segments
     *
     * @param  bool $toArray return segments as LineString or array of start and end points
     * @return LineString[]|array[Point]
     */
    public function explode(bool $toArray = false): array
    {
        $points = $this->getPoints();
        $numPoints = count($points);
        if ($numPoints < 2) {
            return [];
        }
        $parts = [];
        for ($i = 1; $i < $numPoints; ++$i) {
            $segment = [$points[$i - 1], $points[$i]];
            $parts[] = $toArray ? $segment : new LineString($segment);
        }
        return $parts;
    }

    /**
     * Checks that LineString is a Simple Geometry
     *
     * @return bool
     */
    public function isSimple(): bool
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->isSimple();
            // @codeCoverageIgnoreEnd
        }
        
        $segments = $this->explode(true);
        foreach ($segments as $i => $segment) {
            foreach ($segments as $j => $checkSegment) {
                if ($i != $j) {
                    if (Geometry::segmentIntersects($segment[0], $segment[1], $checkSegment[0], $checkSegment[1])) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->checkValidity()['valid'];
        }
        
        // there should only be unique points but there have to be at least 2 of them
        $pts = [];
        foreach ($this->components as $pt) {
            if ($pt->isEmpty()) {
                continue;
            }
            $pts[] = $pt->asText();
        }
        if (count(array_unique($pts)) < 2) {
            return false;
        }
        
        return $this->isSimple();
    }

    /**
     * @param  LineString $segment
     * @return bool
     */
    public function lineSegmentIntersect($segment): bool
    {
        return Geometry::segmentIntersects(
            $this->startPoint(),
            $this->endPoint(),
            $segment->startPoint(),
            $segment->endPoint()
        );
    }

    /**
     * @param  Geometry|Collection $geometry
     * @return float|null
     */
    public function distance(Geometry $geometry)
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->distance($geometry->getGeos());
            // @codeCoverageIgnoreEnd
        }

        if ($geometry->geometryType() === Geometry::POINT) {
            // This is defined in the Point class nicely
            return $geometry->distance($this);
        }
        
        if ($geometry->geometryType() === Geometry::LINESTRING) {
            $distance = PHP_INT_MAX;
            $geometrySegments = $geometry->explode();
            foreach ($this->explode() as $seg1) {
                // @var LineString $seg2
                foreach ($geometrySegments as $seg2) {
                    if ($seg1->lineSegmentIntersect($seg2)) {
                        return 0.0;
                    }
                    // Because line-segments are straight, the shortest distance will occur at an endpoint.
                    // If they are parallel, an endpoint calculation is still accurate.
                    $distance = min(
                        $distance,
                        $seg1->startPoint()->distance($seg2),
                        $seg1->endPoint()->distance($seg2),
                        $seg2->startPoint()->distance($seg1),
                        $seg2->endPoint()->distance($seg1)
                    );

                    if ($distance === 0.0) {
                        return 0.0;
                    }
                }
            }
            return (float) $distance;
        }
        
        // It can be treated as a collection
        return parent::distance($geometry);
    }
}
