<?php
namespace geoPHP\Geometry;

use geoPHP\Exception\InvalidGeometryException;
use geoPHP\geoPHP;

/**
 * A Point is a 0-dimensional geometric object and represents a single location in coordinate space.
 * A Point has an x-coordinate value, a y-coordinate value.
 * If called for by the associated Spatial Reference System, it may also have coordinate values for z and m.
 */
class Point extends Geometry
{

    /**
     * @var float|null
     */
    public $x = null;

    /**
     * @var float|null
     */
    public $y = null;

    /**
     * @var float|null
     */
    public $z = null;

    /**
     * @var float|null
     */
    public $m = null;

    /**
     * Constructor
     *
     * @param  mixed $x The x coordinate (or longitude)
     * @param  mixed $y The y coordinate (or latitude)
     * @param  mixed $z The z coordinate (or altitude) - optional
     * @param  mixed $m Measure - optional
     * @throws InvalidGeometryException
     */
    public function __construct($x = null, $y = null, $z = null, $m = null)
    {
        // If X or Y is null than it is an empty point
        if ($x !== null && $y !== null) {
            // Basic validation on x and y
            if (!is_numeric($x) || !is_numeric($y)) {
                throw new InvalidGeometryException("Cannot construct Point. x and y should be numeric");
            }

            // Convert to float in case they are passed in as a string or integer etc.
            $this->x = floatval($x);
            $this->y = floatval($y);
        
            // Check to see if this point has Z (height) value
            if ($z !== null) {
                if (!is_numeric($z)) {
                    throw new InvalidGeometryException("Cannot construct Point. z should be numeric");
                }
                $this->z = floatval($z);
            }

            // Check to see if this is a measure
            if ($m !== null) {
                if (!is_numeric($m)) {
                    throw new InvalidGeometryException("Cannot construct Point. m should be numeric");
                }
                $this->m = floatval($m);
            }
        }
    }

    /**
     * @param  array<mixed> $coordinates
     * @return Point
     * @throws InvalidGeometryException
     */
    public static function fromArray(array $coordinates)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return (new \ReflectionClass(get_called_class()))->newInstanceArgs($coordinates);
    }

    /**
     * @return string "Point"
     */
    public function geometryType(): string
    {
        return Geometry::POINT;
    }

    /**
     * @return int 0
     */
    public function dimension(): int
    {
        return 0;
    }

    /**
     * Get X (longitude) coordinate
     *
     * @return float|null The X coordinate
     */
    public function getX()
    {
        return $this->x ?? null;
    }

    /**
     * Returns Y (latitude) coordinate
     *
     * @return float|null The Y coordinate
     */
    public function getY()
    {
        return $this->y ?? null;
    }

    /**
     * Returns Z (altitude) coordinate
     *
     * @return float|null The Z coordinate or NULL if it is not a 3D point.
     */
    public function getZ()
    {
        return $this->z ?? null;
    }

    /**
     * Returns M (measured) value
     *
     * @return float|null The measured value
     */
    public function getM()
    {
        return $this->m ?? null;
    }

    /**
     * check if Geometry has a measure value
     *
     * @return bool true if collection has measure values
     */
    public function isMeasured(): bool
    {
        return $this->getM() !== null;
    }

    /**
     * check if Geometry has Z (altitude) coordinate
     *
     * @return bool true if geometry has a Z-value
     */
    public function hasZ(): bool
    {
        return $this->getZ() !== null;
    }
    
    /**
     * Inverts x and y coordinates
     * Useful if old applications still use lng lat
     *
     * @return self
     * */
    public function invertXY()
    {
        $x = $this->x;
        $this->x = $this->y;
        $this->y = $x;
        $this->setGeos(null);
        return $this;
    }

    /**
     * A point's centroid is itself
     *
     * @return Point object itself
     */
    public function getCentroid(): Point
    {
        return $this;
    }

    /**
     * @return array{'minx'?:float|null, 'miny'?:float|null, 'maxx'?:float|null, 'maxy'?:float|null}
     */
    public function getBBox(): array
    {
        if ($this->isEmpty()) {
            return [];
        }
        
        return [
            'maxy' => $this->getY(),
            'miny' => $this->getY(),
            'maxx' => $this->getX(),
            'minx' => $this->getX(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function asArray(): array
    {
        if ($this->isEmpty()) {
            return [null, null];
        }
        if (!$this->hasZ()) {
            return !$this->isMeasured() ? [$this->x, $this->y] : [$this->x, $this->y, null, $this->m];
        }
        
        return !$this->isMeasured() ? [$this->x, $this->y, $this->z] : [$this->x, $this->y, $this->z, $this->m];
    }

    /**
     * The boundary of a Point is the empty set.
     *
     * @return GeometryCollection
     */
    public function boundary(): Geometry
    {
        return new GeometryCollection();
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->x === null;
    }

    /**
     * @return int Returns always 1
     */
    public function numPoints(): int
    {
        return 1;
    }

    /**
     * @return Point[]
     */
    public function getPoints(): array
    {
        return [$this];
    }

    /**
     * @return Point[]
     */
    public function getComponents(): array
    {
        return [$this];
    }
    
    /**
     * Determines weather the specified geometry is spatially equal to this Point
     *
     * Because of limited floating point precision in PHP, equality can be only approximated
     *
     * @see: http://php.net/manual/en/function.bccomp.php
     * @see: http://php.net/manual/en/language.types.float.php
     *
     * @param Point|Geometry $geometry
     *
     * @return bool
     */
    public function equals(Geometry $geometry): bool
    {
        return $geometry->geometryType() === Geometry::POINT
            ? (abs(($this->getX() ?? 0) - ($geometry->getX() ?? 0)) <= 1.0E-9 &&
                abs(($this->getY() ?? 0) - ($geometry->getY() ?? 0)) <= 1.0E-9 &&
                abs(($this->getZ() ?? 0) - ($geometry->getZ() ?? 0)) <= 1.0E-9)
            : false;
    }

    /**
     * @return bool always true
     */
    public function isSimple(): bool
    {
        return true;
    }
    
    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return true;
    }

    /**
     * resets/empties all values
     * @return void
     */
    public function flatten()
    {
        unset($this->z);
        unset($this->m);
        $this->setGeos(null);
    }

    /**
     * @param  Geometry|Collection $geometry
     * @return float|null
     */
    public function distance(Geometry $geometry)
    {
        if ($this->isEmpty() || $geometry->isEmpty()) {
            return null;
        }
        
        // use GEOS if available
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            $geosObj2 = $geometry->getGeos();
            return is_object($geosObj2) ? $geosObj->distance($geosObj2) : null;
            // @codeCoverageIgnoreEnd
        }
        
        switch ($geometry->geometryType()) {
            // Point
            case Geometry::POINT:
                return sqrt(
                    pow(($this->getX() - $geometry->getX()), 2) +
                    pow(($this->getY() - $geometry->getY()), 2)
                );
            
            // MultiPoint, GeometryCollection
            case Geometry::MULTI_POINT:
            case Geometry::GEOMETRY_COLLECTION:
                return $this->distanceToMultiPointOrCollection($geometry);
                
            // LineString, Polygon, MultiLineString, MultiPolygon.
            default:
                return $this->distanceToMultiGeometry($geometry);
        }
    }
    
    /**
     * @param  Geometry|Collection $geometry
     * @return float|null
     */
    private function distanceToMultiPointOrCollection($geometry)
    {
        $distance = null;
        foreach ($geometry->getComponents() as $component) {
            $checkDistance = $this->distance($component);
            if ($checkDistance === 0.0) {
                return 0.0;
            }
            if ($checkDistance === null) {
                continue;
            }
            $distance = $distance ?? $checkDistance;

            if ($checkDistance < $distance) {
                $distance = $checkDistance;
            }
        }
        
        return $distance;
    }
    
    /**
     * @param  Geometry $geometry
     * @return float|null
     */
    private function distanceToMultiGeometry(Geometry $geometry)
    {
        // The nearest point might be a vertex, but it could also be somewhere along a line-segment
        // that makes up the geometry (between vertices).
        // Here we brute force check all line segments that make up these geometries.
        $distance = null;
        /** @var Point[] $seg */
        foreach ($geometry->explode(true) as $seg) {
            // As per http://stackoverflow.com/questions/849211/shortest-distance-between-a-point-and-a-line-segment
            // and http://paulbourke.net/geometry/pointline/
            $x1 = $seg[0]->getX();
            $y1 = $seg[0]->getY();
            $x2 = $seg[1]->getX();
            $y2 = $seg[1]->getY();
            $px = $x2 - $x1;
            $py = $y2 - $y1;
            $d = ($px * $px) + ($py * $py);
            if ($d == 0) {
                // Line-segment's endpoints are identical. This is merely a point masquerading a line-segment.
                $checkDistance = $this->distance($seg[1]);
            } else {
                $x3 = $this->getX();
                $y3 = $this->getY();
                $u = ((($x3 - $x1) * $px) + (($y3 - $y1) * $py)) / $d;
                if ($u > 1) {
                    $u = 1;
                }
                if ($u < 0) {
                    $u = 0;
                }
                $x = $x1 + ($u * $px);
                $y = $y1 + ($u * $py);
                $dx = $x - $x3;
                $dy = $y - $y3;
                $checkDistance = sqrt(($dx * $dx) + ($dy * $dy));
            }
            
            if ($checkDistance === 0.0) {
                return 0.0;
            }
            if ($checkDistance === null) {
                continue;
            }
            $distance = ($distance ?? $checkDistance);
            
            if ($checkDistance < $distance) {
                $distance = $checkDistance;
            }
        }
        
        return $distance;
    }

    /**
     * @return float|int|null
     */
    public function minimumZ()
    {
        return $this->z;
    }

    /**
     * @return float|int|null
     */
    public function maximumZ()
    {
        return $this->z;
    }

    /**
     * @return float|int|null
     */
    public function minimumM()
    {
        return $this->m;
    }

    /**
     * @return float|int|null
     */
    public function maximumM()
    {
        return $this->m;
    }

    /**
     * @return float 0.0
     */
    public function getArea(): float
    {
        return 0.0;
    }

    /**
     * @return float 0.0
     */
    public function getLength(): float
    {
        return 0.0;
    }

    /**
     * @return float 0.0
     */
    public function length3D(): float
    {
        return 0.0;
    }

    /**
     * @param  float|int $radius
     * @return float 0.0
     */
    public function greatCircleLength($radius = geoPHP::EARTH_WGS84_SEMI_MAJOR_AXIS): float
    {
        return 0.0;
    }

    /**
     * @return float 0.0
     */
    public function haversineLength(): float
    {
        return 0.0;
    }

    /**
     * @return int 1
     */
    public function numGeometries(): int
    {
        return $this->isEmpty() ? 0 : 1;
    }

    /**
     * @param  int $n
     * @return Point|null
     */
    public function geometryN(int $n)
    {
        return $n === 1 ? $this : null;
    }

    /**
     * @return bool true
     */
    public function isClosed(): bool
    {
        return true;
    }

    /**
     * Not valid for this geometry type
     *
     * @param  bool $toArray default false
     * @return array{}
     */
    public function explode(bool $toArray = false): array
    {
        return [];
    }
    
    /**
     * @param int|float $dx
     * @param int|float $dy
     * @param int|float $dz
     * @return void
     */
    public function translate($dx = 0, $dy = 0, $dz = 0)
    {
        $this->x += $dx;
        $this->y += $dy;
        $this->z += $dz;
    }
}
