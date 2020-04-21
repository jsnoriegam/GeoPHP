<?php
namespace geoPHP\Geometry;

use geoPHP\geoPHP;
use geoPHP\Exception\UnsupportedMethodException;

/**
 * Geometry is the root class of the hierarchy. Geometry is an abstract (non-instantiable) class.
 *
 * OGC 06-103r4 6.1.2 specification:
 * The instantiable subclasses of Geometry defined in this Standard are restricted to
 * 0, 1 and 2-dimensional geometric objects that exist in 2, 3 or 4-dimensional coordinate space.
 *
 * Geometry values in R^2 have points with coordinate values for x and y.
 * Geometry values in R^3 have points with coordinate values for x, y and z or for x, y and m.
 * Geometry values in R^4 have points with coordinate values for x, y, z and m.
 * The interpretation of the coordinates is subject to the coordinate reference systems associated to the point.
 * All coordinates within a geometry object should be in the same coordinate reference systems.
 * Each coordinate shall be unambiguously associated to a coordinate reference system
 * either directly or through its containing geometry.
 *
 * The z coordinate of a point is typically, but not necessarily, represents altitude or elevation.
 * The m coordinate represents a measurement.
 */
abstract class Geometry
{

    /**
     * Type constants
     */
    const POINT = 'Point';
    const LINESTRING = 'LineString';
    const POLYGON = 'Polygon';
    const MULTI_POINT = 'MultiPoint';
    const MULTI_LINESTRING = 'MultiLineString';
    const MULTI_POLYGON = 'MultiPolygon';
    const GEOMETRY_COLLECTION = 'GeometryCollection';
    const CIRCULAR_STRING = 'CircularString';
    const COMPOUND_CURVE = 'CompoundCurve';
    const CURVE_POLYGON = 'CurvePolygon';
    const MULTI_CURVE = 'MultiCurve'; // Abstract
    const MULTI_SURFACE = 'MultiSurface'; // Abstract
    const CURVE = 'Curve'; // Abstract
    const SURFACE = 'Surface'; // Abstract
    const POLYHEDRAL_SURFACE = 'PolyhedralSurface';
    const TIN = 'TIN';
    const TRIANGLE = 'Triangle';

    /**
     * @var bool True if Geometry has Z (altitude) value
     */
    protected $hasZ = false;

    /**
     * @var bool True if Geometry has M (measure) value
     */
    protected $isMeasured = false;

    /**
     * @var int|null $srid Spatial Reference System Identifier (http://en.wikipedia.org/wiki/SRID)
     */
    protected $srid;

    /**
     * @var mixed|null Custom (meta)data
     */
    protected $data;

    /**
     * @var \GEOSGeometry|null
     */
    private $geos;

    /****************************************
     * Basic methods on geometric objects  *
     * **************************************/

    /**
     * The inherent dimension of the geometric object, which must be less than or equal to the coordinate dimension.
     * In non-homogeneous collections, this will return the largest topological dimension of the contained objects.
     *
     * @return int
     */
    abstract public function dimension(): int;

    /**
     * Returns the name of the instantiable subtype of Geometry of which the geometric object is an instantiable member.
     *
     * @return string
     */
    abstract public function geometryType(): string;

    /**
     * Returns true whether the set of points covered by this Geometry is empty.
     *
     * @return bool
     */
    abstract public function isEmpty(): bool;

    /**
     * Tests whether this geometry is simple. The SFS definition of simplicity follows the general rule that a Geometry
     * is simple if it has no points of self-tangency, self-intersection or other anomalous points.
     * Be aware: a geometry can be simple but also not valid! E.g. "LINESTRING(1 1,1 1)" or "POLYGON((1 1,1 1,1 1,1 1))"
     * are simple but not valid.
     *
     * Simplicity is defined for each Geometry subclass as follows:
     * - Valid polygonal geometries are simple, since their rings must not self-intersect. isSimple tests for this
     *   condition and reports false if it is not met. (This is a looser test than checking for validity).
     * - Linear rings have the same semantics.
     * - Linear geometries are simple if they do not self-intersect at points except on its end-points.
     * - Zero-dimensional geometries (MultiPoints) are simple if they have no repeated points.
     * - Empty Geometries are always simple.
     *
     * @return bool
     */
    abstract public function isSimple(): bool;

    /**
     * Returns the boundary, or an empty geometry of appropriate dimension if this Geometry is empty.
     * In the case of zero-dimensional geometries, an empty GeometryCollection is returned.
     * The boundary of a Geometry is a set of Geometries of the next lower dimension.
     * By default, the boundary of a collection is the boundary of it's components.
     *
     * @return Geometry the closure of the combinatorial boundary of this Geometry
     */
    abstract public function boundary(): Geometry;

    /**
     * @return Geometry[]
     */
    abstract public function getComponents(): array;

    /*************************************************
     * Methods applicable on certain geometry types *
     ************************************************/

    /**
     * @see        Geometry::getArea()
     * @deprecated since version 1.4
     */
    public function area(): float
    {
        return $this->getArea();
    }

    /**
     * @see        Geometry::getCentroid()
     * @deprecated since version 1.4
     */
    public function centroid(): Point
    {
        return $this->getCentroid();
    }

    /**
     * @see        Geometry::getLength()
     * @deprecated since version 1.4
     */
    public function length(): float
    {
        return $this->getLength();
    }

    abstract public function length3D(): float;

    /**
     * @see        Geometry::getX()
     * @deprecated since version 1.4
     */
    public function x()
    {
        return $this->getX();
    }

    /**
     * @see        Geometry::getY()
     * @deprecated since version 1.4
     */
    public function y()
    {
        return $this->getY();
    }

    /**
     * @see        Geometry::getZ()
     * @deprecated since version 1.4
     */
    public function z()
    {
        return $this->getZ();
    }

    /**
     * @see        Geometry::getM()
     * @deprecated since version 1.4
     */
    public function m()
    {
        return $this->getM();
    }

    /**
     * @return int
     */
    abstract public function numGeometries(): int;

    /**
     * @param  int $n One-based index.
     * @return Geometry|null The geometry or null if not found.
     */
    abstract public function geometryN(int $n);

    /**
     * @return Point|null
     */
    public function startPoint()
    {
        return null;
    }

    /**
     * @return Point|null
     */
    public function endPoint()
    {
        return null;
    }

    /**
     * @return bool
     * @throws UnsupportedMethodException
     */
    public function isRing(): bool
    {
        throw new UnsupportedMethodException(
                get_called_class() . '::isRing',
            null,
            "It should only be called on a linear feature."
        );
    }

    abstract public function isClosed(): bool;

    abstract public function numPoints(): int;

    /**
     * @param  int $n Nth point
     * @return Point|null
     */
    public function pointN(int $n)
    {
        return null;
    }

    /**
     * @return Geometry|null
     */
    public function exteriorRing()
    {
        return null;
    }

    /**
     * @return int|null
     */
    public function numInteriorRings()
    {
        return null;
    }

    /**
     * @return Geometry|null
     */
    public function interiorRingN(int $n)
    {
        return null;
    }

    /**
     * Returns the shortest distance between g1 and g2.
     * The Geometry inputs for distance can be any combination of 2D or 3D geometries.
     * If both geometries are 3D then a 3D distance is computed.
     * If both geometries are 2D then a 2D distance is computed.
     * If one geometry is 2D and the other is 3D then a 2D distance is computed (as if the 2D object was infinitely
     * extended along the Z axis).
     *
     * @return float the distance between the geometries. 0.0 if input geometry is empty.
     */
    abstract public function distance(Geometry $geom): float;

    abstract public function equals(Geometry $geom): bool;

    // Abstract: Non-Standard
    // ----------------------------------------------------------

    abstract public function getBBox(): array;

    abstract public function asArray(): array;

    /**
     * @return Point[]
     */
    abstract public function getPoints(): array;

    abstract public function invertXY();

    /**
     * @param  bool $toArray return underlying components as LineStrings/Points or as array. "explode(true)" is faster
     * @return LineString[]|Point[][]
     */
    abstract public function explode(bool $toArray = false): array;

    abstract public function greatCircleLength(float $radius = geoPHP::EARTH_WGS84_SEMI_MAJOR_AXIS): float; //meters

    abstract public function haversineLength(): float; //degrees

    // 3D to 2D
    abstract public function flatten();
    
    // Elevations statistics
    /**
     * @return int|float|null
     */
    public function minimumZ()
    {
        return null;
    }

    /**
     * @return int|float|null
     */
    public function maximumZ()
    {
        return null;
    }

    /**
     * @return int|float|null
     */
    public function minimumM()
    {
        return null;
    }

    /**
     * @return int|float|null
     */
    public function maximumM()
    {
        return null;
    }
    
    /**
     * @return int|float|null
     */
    public function zDifference()
    {
        return null;
    }

    /**
     * @return int|float|null
     */
    public function elevationGain($verticalTolerance = 0)
    {
        return null;
    }

    /**
     * @return int|float|null
     */
    public function elevationLoss($verticalTolerance = 0)
    {
        return null;
    }

    // Public: Standard -- Common to all geometries
    // ----------------------------------------------------------

    /**
     * @see        Geometry::hasZ()
     * @deprecated since version 1.4
     */
    public function is3D(): bool
    {
        return $this->hasZ();
    }

    /**
     * check if Geometry has a measure value
     *
     * @return bool True if collection has measure value
     */
    public function isMeasured(): bool
    {
        return $this->isMeasured;
    }

    /**
     * @param int $srid Spatial Reference System Identifier
     */
    public function setSRID($srid)
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            $this->getGeos()->setSRID($srid);
            // @codeCoverageIgnoreEnd
        }
        $this->srid = $srid;
    }

    /**
     * Adds custom data to the geometry
     *
     * @param string|array $property The name of the data or an associative array
     * @param mixed|null   $value    The data. Can be any type (string, integer, array, etc.)
     */
    public function setData($property, $value = null)
    {
        if (is_array($property)) {
            $this->data = $property;
        } else {
            $this->data[$property] = $value;
        }
    }

    /**
     * Returns the requested data by property name, or all data of the geometry
     *
     * @param  string|null $property The name of the data. If omitted, all data will be returned
     * @return mixed|null The data or null if not exists
     */
    public function getData($property = null)
    {
        if ($property) {
            return $this->hasDataProperty($property) ? $this->data[$property] : null;
        }
        
        return $this->data;
    }

    /**
     * Tells whether the geometry has data with the specified name
     *
     * @param  string $property The name of the property
     * @return bool True if the geometry has data with the specified name, otherwise false.
     */
    public function hasDataProperty($property): bool
    {
        return array_key_exists($property, $this->data ?: []);
    }

    /**
     * returns the envelope of a geometry or the geometry itself if it is a point
     *
     * @return \geoPHP\Geometry\Polygon|\geoPHP\Geometry\type|$this
     */
    public function envelope()
    {
        if ($this->isEmpty()) {
            $type = '\\geoPHP\\Geometry\\' . $this->geometryType();
            return new $type();
        }
        if ($this->geometryType() === Geometry::POINT) {
            return $this;
        }

        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->envelope());
            // @codeCoverageIgnoreEnd
        }

        $boundingBox = $this->getBBox();
        $points = [
            new Point($boundingBox['maxx'], $boundingBox['miny']),
            new Point($boundingBox['maxx'], $boundingBox['maxy']),
            new Point($boundingBox['minx'], $boundingBox['maxy']),
            new Point($boundingBox['minx'], $boundingBox['miny']),
            new Point($boundingBox['maxx'], $boundingBox['miny']),
        ];
        
        return new Polygon([new LineString($points)]);
    }

    /**
     * Public: Non-Standard -- Common to all geometries
     * $this->out($format, $other_args);
     *
     * @return string
     */
    public function out(): string
    {
        $args = func_get_args();
        $format = strtolower(array_shift($args));
        
        // Big Endian WKB
        if (strstr($format, 'xdr')) {
            $args[] = true;
            $format = str_replace('xdr', '', $format);
        }

        $processorType = '\\geoPHP\\Adapter\\' . geoPHP::getAdapterMap()[$format];
        $processor = new $processorType;
        array_unshift($args, $this);
        $result = call_user_func_array([$processor, 'write'], $args);
        
        return $result;
    }

    /**
     * @return int
     */
    public function coordinateDimension(): int
    {
        return 2 + ($this->hasZ() ? 1 : 0) + ($this->isMeasured() ? 1 : 0);
    }

    /**
     * Utility function to check if any line segments intersect
     * Derived from @source http://stackoverflow.com/questions/563198/how-do-you-detect-where-two-line-segments-intersect
     *
     * @param  Point $segment1Start
     * @param  Point $segment1End
     * @param  Point $segment2Start
     * @param  Point $segment2End
     * @return bool
     */
    public static function segmentIntersects($segment1Start, $segment1End, $segment2Start, $segment2End): bool
    {
        $p0x = $segment1Start->getX();
        $p0y = $segment1Start->getY();
        $p1x = $segment1End->getX();
        $p1y = $segment1End->getY();
        $p2x = $segment2Start->getX();
        $p2y = $segment2Start->getY();
        $p3x = $segment2End->getX();
        $p3y = $segment2End->getY();
        
        $s1x = $p1x - $p0x;
        $s1y = $p1y - $p0y;
        $s2x = $p3x - $p2x;
        $s2y = $p3y - $p2y;

        $fps = (-$s2x * $s1y) + ($s1x * $s2y);
        $fpt = (-$s2x * $s1y) + ($s1x * $s2y);

        if ($fps == 0 || $fpt == 0) {
            return false;
        }

        $s = (-$s1y * ($p0x - $p2x) + $s1x * ($p0y - $p2y)) / $fps;
        $t = ($s2x * ($p0y - $p2y) - $s2y * ($p0x - $p2x)) / $fpt;

        // Return true if collision is detected
        return ($s > 0 && $s < 1 && $t > 0 && $t < 1);
        
        /*
        // x und y is for line 1
        // u und v is for line 2
        $x1 = $segment1Start->getX();
        $y1 = $segment1Start->getY();
        $x2 = $segment1End->getX();
        $y2 = $segment1End->getY();
        $u1 = $segment2Start->getX();
        $v1 = $segment2Start->getY();
        $u2 = $segment2End->getX();
        $v2 = $segment2End->getY();

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        // avoid division with 0
        if ($dx != 0 && ($u2 - $u1) != 0) {
            $b1 = $dy / $dx;
            $b2 = ($v2 - $v1) / ($u2 - $u1);
        } else {
            $b1 = $b2 = 1;
        }

        // lines with identical inclines cannot cross, but can lie on top of each other.
        // So calculation is done with their distances.
        if ($b1 == $b2) {
            if (($dx * ($v1 - $y1) - ($u1 - $x1) * $dy) == 0) {
                return true;
            }
            // $u1, $v1 are on one line
            elseif (($dx * ($v2 - $y1) - ($u2 - $x1) * $dy) == 0) {
                return true;
            }

            return false;
        }

        $a1 = $y1 - $b1 * $x1;
        $a2 = $v1 - $b2 * $u1;

        $xi = -($a1 - $a2) / ($b1 - $b2); #(C)
        $yi = $a1 + $b1 * $xi;

        # lines cross at $xi,$yi
        if (($x1 - $xi) * ($xi - $x2) >= 0 &&
            ($u1 - $xi) * ($xi - $u2) >= 0 &&
            ($y1 - $yi) * ($yi - $y2) >= 0 &&
            ($v1 - $yi) * ($yi - $v2) >= 0) {
            return true;
        }

        return false;*/
    }

    // Public: Aliases
    // ------------------------------------------------

    /**
     * check if Geometry has Z (altitude) coordinate
     *
     * @return bool true if collection has a Z-value
     */
    public function hasZ(): bool
    {
        return $this->hasZ;
    }

    /**
     * @return float|int|null
     * @throws UnsupportedMethodException
     */
    public function getX()
    {
        throw new UnsupportedMethodException(
            get_called_class() . '::getX',
            null,
            "Geometry has to be a point."
        );
    }

    /**
     * @return float|int|null
     * @throws UnsupportedMethodException
     */
    public function getY()
    {
        throw new UnsupportedMethodException(
             get_called_class() . '::getY',
            null,
            "Geometry has to be a point."
        );
    }

    /**
     * @return float|int|null
     * @throws UnsupportedMethodException
     */
    public function getZ()
    {
        throw new UnsupportedMethodException(
             get_called_class() . '::getZ',
            null,
            "Geometry has to be a point."
        );
    }

    /**
     * @return float|int|null
     * @throws UnsupportedMethodException
     */
    public function getM()
    {
        throw new UnsupportedMethodException(
             get_called_class() . '::getM',
            null,
            "Geometry has to be a point."
        );
    }

    public function getBoundingBox(): array
    {
        return $this->getBBox();
    }

    public function dump(): array
    {
        return $this->getComponents();
    }
    
    /**
     * @return \geoPHP\Geometry\Point
     */
    abstract public function getCentroid(): Point;

    /**
     * Returns the area of this Geometry.
     * Areal Geometries have a non-zero area. They override this function to compute the area. Others return 0.0
     */
    abstract public function getArea(): float;
    
    /**
     * Returns the length of this Geometry.
     * Linear geometries return their length. Areal geometries return their perimeter. Others return 0.0
     */
    public function getLength(): float
    {
        return 0.0;
    }

    /**
     * @see        Geometry::getGeos()
     * @deprecated since version 1.4
     */
    public function geos()
    {
        return $this->getGeos();
    }

    public function getGeomType(): string
    {
        return $this->geometryType();
    }

    public function getSRID()
    {
        return $this->srid;
    }

    public function asText(): string
    {
        return $this->out('wkt');
    }

    public function asBinary(): string
    {
        return $this->out('wkb');
    }

    /****************************************
     * Public: GEOS Only Functions  *
     * **************************************/
    
    /**
     * Returns the GEOS representation of Geometry if GEOS is installed
     *
     * @return             \GEOSGeometry|false
     * @codeCoverageIgnore
     */
    public function getGeos()
    {
        // If it's already been set, just return it
        if ($this->geos && geoPHP::geosInstalled()) {
            return $this->geos;
        }
        // It hasn't been set yet, generate it
        if (geoPHP::geosInstalled()) {
            /** @noinspection PhpUndefinedClassInspection */
            $reader = new \GEOSWKBReader();
            /** @noinspection PhpUndefinedMethodInspection */
            $this->geos = $reader->read($this->out('wkb'));
        } else {
            $this->geos = false;
        }
        return $this->geos;
    }

    /**
     * @param GEOSGeometry $geos
     */
    public function setGeos($geos)
    {
        $this->geos = $geos;
    }

    /**
     * @return             Geometry|Point|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function pointOnSurface()
    {
        if ($this->isEmpty()) {
            return new Point();
        }
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->pointOnSurface());
        }
        // help for implementation: http://gis.stackexchange.com/questions/76498/how-is-st-pointonsurface-calculated
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function equalsExact(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->equalsExact($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry    $geometry
     * @param              string|null $pattern
     * @return             string|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function relate(Geometry $geometry, $pattern = null)
    {
        if ($this->getGeos()) {
            if ($pattern) {
                /** @noinspection PhpUndefinedMethodInspection */
                return $this->getGeos()->relate($geometry->getGeos(), $pattern);
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                return $this->getGeos()->relate($geometry->getGeos());
            }
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @return             array ['valid' => (bool)..., 'reason' => (string)...]
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function checkValidity(): array
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->checkValidity();
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function isValid(): bool
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->checkValidity()['valid'];
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              float|int $distance
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function buffer($distance)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->buffer($distance));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function intersection(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->intersection($geometry->getGeos()));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * @param              int|float $x1
     * @param              int|float $y1
     * @param              int|float $x2
     * @param              int|float $y2
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function clipByRect($x1, $y1, $x2, $y2)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->clipByRect($x1, $y1, $x2, $y2));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function convexHull()
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->convexHull());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              float|int  $tolerance snapping tolerance to use for improved robustness
     * @param              bool|false $onlyEdges if true will return a MULTILINESTRING, otherwise (the default) it will return a
     *                                           GEOMETRYCOLLECTION containing triangular POLYGONs.
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function delaunayTriangulation($tolerance = 0.0, bool $onlyEdges = false)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->delaunayTriangulation($tolerance, $onlyEdges));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * @param              float|int  $tolerance snapping tolerance to use for improved robustness
     * @param              bool|false $onlyEdges if true will return a MULTILINESTRING, otherwise (the default) it will return a
     *                                           GEOMETRYCOLLECTION containing POLYGONs.
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function voronoiDiagram($tolerance = 0.0, bool $onlyEdges = false)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->voronoiDiagram($tolerance, $onlyEdges));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * @param              Geometry $geometry
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function difference(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->difference($geometry->getGeos()));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * Snaps the vertices and segments of a geometry to another Geometry's vertices. A snap distance tolerance is used
     * to control where snapping is performed. The result geometry is the input geometry with the vertices snapped.
     * If no snapping occurs then the input geometry is returned unchanged.
     *
     * Snapping one geometry to another can improve robustness for overlay operations by eliminating nearly-coincident
     * edges (which cause problems during noding and intersection calculation).
     *
     * Too much snapping can result in invalid topology being created, so the number and location of snapped vertices
     * is decided using heuristics to determine when it is safe to snap. This can result in some potential snaps being
     * omitted, however.
     *
     * @param              Geometry $geometry
     * @param              float    $snapTolerance
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function snapTo(Geometry $geometry, $snapTolerance = 0.0)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->snapTo($geometry->getGeos(), $snapTolerance));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function symDifference(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->symDifference($geometry->getGeos()));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * Can pass in a geometry or an array of geometries
     *
     * @param              Geometry $geometry
     * @return             bool|mixed|null|GeometryCollection
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function union(Geometry $geometry)
    {
        if ($this->getGeos()) {
            if (is_array($geometry)) {
                $geom = $this->getGeos();
                foreach ($geometry as $item) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $geom = $geom->union($item->geos());
                }
                return geoPHP::geosToGeometry($geom);
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                return geoPHP::geosToGeometry($this->getGeos()->union($geometry->getGeos()));
            }
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              float      $tolerance
     * @param              bool|false $preserveTopology
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function simplify($tolerance, $preserveTopology = false)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->simplify($tolerance, $preserveTopology));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param int|float $dx
     * @param int|float $dy
     * @param int|float $dz
     * @return void
     */
    abstract public function translate($dx = 0, $dy = 0, $dz = 0);
    
    /**
     * The function attempts to create a valid representation of a given invalid geometry without losing any of the
     * input vertices. Already-valid geometries are returned without further intervention.
     * Supported inputs are: POINTS, MULTIPOINTS, LINESTRINGS, MULTILINESTRINGS, POLYGONS, MULTIPOLYGONS and
     * GEOMETRYCOLLECTIONS containing any mix of them.
     * In case of full or partial dimensional collapses, the output geometry may be a collection of lower-to-equal
     * dimension geometries or a geometry of lower dimension. Single polygons may become multi-geometries in case of
     * self-intersections.
     *
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function makeValid()
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->makeValid());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * Creates an areal geometry formed by the constituent linework of given geometry. The return type can be a
     * Polygon or MultiPolygon, depending on input. If the input lineworks do not form polygons NULL is returned.
     * The inputs can be LINESTRINGS, MULTILINESTRINGS, POLYGONS, MULTIPOLYGONS and GeometryCollections.
     *
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function buildArea()
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->buildArea());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function disjoint(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->disjoint($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function touches(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->touches($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function intersects(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->intersects($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function crosses(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->crosses($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function within(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->within($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function contains(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->contains($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(get_called_class() . '::contains');
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function overlaps(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->overlaps($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(get_called_class() . '::overlaps');
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function covers(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->covers($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(get_called_class() . '::covers');
    }

    /**
     * @param              Geometry $geometry
     * @return             bool
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function coveredBy(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->coveredBy($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(get_called_class() . '::coveredBy');
    }

    /**
     * @param              Geometry $geometry
     * @return             float
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function hausdorffDistance(Geometry $geometry)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->hausdorffDistance($geometry->getGeos());
        }
        throw UnsupportedMethodException::geos(get_called_class() . '::hausdorffDistance');
    }

    /**
     * @param float|int $distance
     * @param array     $styleArray
     * styleArray keys supported:
     * - 'quad_segs'
     *       (integer) Number of segments used to approximate a quarter circle (defaults to 8).
     * - 'join'
     *       (float) Join style (defaults to GEOSBUF_JOIN_ROUND)
     * - 'mitre_limit'
     *       (float) mitre ratio limit (only affects joins with GEOSBUF_JOIN_MITRE style)
     *       'miter_limit' is also accepted as a synonym for 'mitre_limit'.
     *
     * @return             Geometry|null
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function offsetCurve($distance = 0.0, array $styleArray = [])
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->offsetCurve($distance, $styleArray));
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
    
    /**
     * @param              Geometry $point
     * @param              null     $normalized
     * @return             \GEOSGeometry
     * @throws             UnsupportedMethodException
     * @codeCoverageIgnore
     */
    public function project(Geometry $point, $normalized = null)
    {
        if ($this->getGeos()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->project($point->getGeos(), $normalized);
        }
        throw UnsupportedMethodException::geos(__METHOD__);
    }
}
