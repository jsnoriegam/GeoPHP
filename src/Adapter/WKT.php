<?php
namespace GeoPHP\Adapter;

use GeoPHP\Geometry\Collection;
use GeoPHP\GeoPHP;
use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\GeometryCollection;
use GeoPHP\Geometry\Point;
use GeoPHP\Geometry\MultiPoint;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\MultiLineString;
use GeoPHP\Geometry\Polygon;
use GeoPHP\Geometry\MultiPolygon;

/**
 * WKT (Well Known Text) Adapter
 */
class WKT implements GeoAdapter
{

    /**
     * @var bool true if geometry has z-values
     */
    protected $hasZ = false;
    
    /**
     * @var bool true if geometry has m-values
     */
    protected $measured = false;

    /**
     * Determines if the given typeString is a valid WKT geometry type
     *
     * @param string $typeString Type to find, eg. "Point", or "LineStringZ"
     * @return string|bool The geometry type if found or false
     */
    public static function isWktType(string $typeString)
    {
        foreach (GeoPHP::getGeometryList() as $geom => $type) {
            if (strtolower((substr($typeString, 0, strlen($geom)))) == $geom) {
                return $type;
            }
        }
        return false;
    }

    /**
     * Read WKT string into geometry objects
     *
     * @param string $wkt A WKT string
     * @return Geometry
     * @throws \Exception
     */
    public function read(string $wkt): Geometry
    {
        $this->hasZ = false;
        $this->measured = false;

        $wkt = trim(strtoupper($wkt));
        $srid = null;
        $m = [];
        // If it contains a ';', then it contains additional SRID data
        if (preg_match('/^SRID=(\d+);/', $wkt, $m)) {
            $srid = $m[1];
            $wkt = substr($wkt, strlen($m[0]));
        }

        // If geos is installed, then we take a shortcut and let it parse the WKT
        if (GeoPHP::geosInstalled()) {
            /** @noinspection PhpUndefinedClassInspection */
            $reader = new \GEOSWKTReader();
            $geometry = GeoPHP::geosToGeometry($reader->read($wkt));
        } else {
            $geometry = $this->parseTypeAndGetData($wkt);
        }
        
        if ($geometry !== null) {
            if ($srid) {
                $geometry->setSRID($srid);
            }
            return $geometry;
        }
        throw new \Exception('Invalid WKT given.');
    }

    /**
     * @param string $wkt
     * @return Geometry|null
     * @throws \Exception
     */
    private function parseTypeAndGetData(string $wkt)
    {
        // geometry type is the first word
        $m = [];
        if (preg_match('#^([a-z]*)#i', $wkt, $m)) {
            $geometryType = $this->isWktType($m[1]);

            $dataString = 'EMPTY';
            if ($geometryType) {
                if (preg_match('#(z{0,1})(m{0,1})\s*\((.*)\)$#i', $wkt, $m)) {
                    $this->hasZ = $m[1];
                    $this->measured = $m[2] ?: null;
                    $dataString = $m[3] ?: $dataString;
                }
                $method = 'parse' . $geometryType;
                return call_user_func([$this, $method], $dataString);
            }
            throw new \Exception('Invalid WKT type "' . $m[1] . '"');
        }
        throw new \Exception('Cannot parse WKT');
    }

    /**
     * @param string $dataString
     * @return Point
     */
    private function parsePoint(string $dataString): Point
    {
        $dataString = trim($dataString);

        // If it's marked as empty, then return an empty point
        if ($dataString === 'EMPTY') {
            return new Point();
        }

        $z = $m = null;
        $parts = explode(' ', $dataString);
        if (isset($parts[2])) {
            if ($this->measured) {
                $m = $parts[2];
            } else {
                $z = $parts[2];
            }
        }
        if (isset($parts[3])) {
            $m = $parts[3];
        }
        return new Point($parts[0], $parts[1], $z, $m);
    }

    /**
     * @param string $dataString
     * @return LineString
     */
    private function parseLineString(string $dataString): LineString
    {
        // If it's marked as empty, then return an empty line
        if ($dataString === 'EMPTY') {
            return new LineString();
        }

        $points = [];
        foreach (explode(',', $dataString) as $part) {
            $points[] = $this->parsePoint($part);
        }
        return new LineString($points);
    }

    /**
     * @param string $dataString
     * @return Polygon
     */
    private function parsePolygon(string $dataString): Polygon
    {
        // If it's marked as empty, then return an empty polygon
        if ($dataString === 'EMPTY') {
            return new Polygon();
        }

        $lines = [];
        $m = [];
        if (preg_match_all('/\(([^)(]*)\)/', $dataString, $m)) {
            foreach ($m[1] as $part) {
                $lines[] = $this->parseLineString($part);
            }
        }
        return new Polygon($lines);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param string $dataString
     * @return MultiPoint
     */
    private function parseMultiPoint(string $dataString): MultiPoint
    {
        // If it's marked as empty, then return an empty MultiPoint
        if ($dataString === 'EMPTY') {
            return new MultiPoint();
        }

        $points = [];
        /* Should understand both forms:
         * MULTIPOINT ((1 2), (3 4))
         * MULTIPOINT (1 2, 3 4)
         */
        foreach (explode(',', $dataString) as $part) {
            $points[] = $this->parsePoint(trim($part, ' ()'));
        }
        return new MultiPoint($points);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param string $dataString
     * @return MultiLineString
     */
    private function parseMultiLineString(string $dataString): MultiLineString
    {
        // If it's marked as empty, then return an empty multi-linestring
        if ($dataString === 'EMPTY') {
            return new MultiLineString();
        }
        $lines = [];
        $m = [];
        if (preg_match_all('/(\([^(]+?\)|EMPTY)/', $dataString, $m)) {
            foreach ($m[1] as $part) {
                $lines[] = $this->parseLineString(trim($part, ' ()'));
            }
        }
        return new MultiLineString($lines);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param string $dataString
     * @return MultiPolygon
     */
    private function parseMultiPolygon(string $dataString): MultiPolygon
    {
        // If it's marked as empty, then return an empty multi-polygon
        if ($dataString === 'EMPTY') {
            return new MultiPolygon();
        }

        $polygons = [];
        $m = [];
        if (preg_match_all('/(\(\([^(].+?\)\)|EMPTY)/', $dataString, $m)) {
            foreach ($m[0] as $part) {
                $polygons[] = $this->parsePolygon($part);
            }
        }
        return new MultiPolygon($polygons);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param string $dataString
     * @return GeometryCollection
     */
    private function parseGeometryCollection(string $dataString): GeometryCollection
    {
        // If it's marked as empty, then return an empty geom-collection
        if ($dataString === 'EMPTY') {
            return new GeometryCollection();
        }

        $geometries = [];
        $m = [];
        while (strlen($dataString) > 0) {
            if ($dataString[0] === ',') {
                $dataString = substr($dataString, 1);
            }
            // Matches the first balanced parenthesis group (or term EMPTY)
            preg_match(
                '/\((?>[^()]+|(?R))*\)|EMPTY/',
                $dataString,
                $m,
                PREG_OFFSET_CAPTURE
            );
            if (!isset($m[0])) {
                // something weird happened, we stop here before running in an infinite loop
                break;
            }
            $cutPosition = strlen($m[0][0]) + $m[0][1];
            $geometries[] = $this->parseTypeAndGetData(trim(substr($dataString, 0, $cutPosition)));
            $dataString = trim(substr($dataString, $cutPosition));
        }

        return new GeometryCollection($geometries);
    }

    /**
     * Serialize geometries into a WKT string.
     *
     * @param Geometry $geometry
     * @return string The WKT string representation of the input geometries
     */
    public function write(Geometry $geometry): string
    {
        // If geos is installed, then we take a shortcut and let it write the WKT
        if (GeoPHP::geosInstalled()) {
            /** @noinspection PhpUndefinedClassInspection */
            $writer = new \GEOSWKTWriter();
            /** @noinspection PhpUndefinedMethodInspection */
            $writer->setRoundingPrecision(14);
            /** @noinspection PhpUndefinedMethodInspection */
            $writer->setTrim(true);
            /** @noinspection PhpUndefinedMethodInspection */
            return $writer->write($geometry->getGeos());
        }
        
        $this->measured = $geometry->isMeasured();
        $this->hasZ = $geometry->hasZ();

        if ($geometry->isEmpty()) {
            return strtoupper($geometry->geometryType()) . ' EMPTY';
        }
        
        $data = $this->extractData($geometry);
        if (!empty($data)) {
            $extension = '';
            if ($this->hasZ) {
                $extension .= 'Z';
            }
            if ($this->measured) {
                $extension .= 'M';
            }
            return strtoupper($geometry->geometryType()) . ($extension ? ' ' . $extension : '') . ' (' . $data . ')';
        }
        return '';
    }

    /**
     * Extract geometry to a WKT string
     *
     * @param Geometry|Collection $geometry A Geometry object
     * @return string
     */
    public function extractData($geometry): string
    {
        $parts = [];
        switch ($geometry->geometryType()) {
            case Geometry::POINT:
                $p = $geometry->getX() . ' ' . $geometry->getY();
                if ($geometry->hasZ()) {
                    $p .= ' ' . $geometry->getZ();
                    $this->hasZ = $this->hasZ || $geometry->hasZ();
                }
                if ($geometry->isMeasured()) {
                    $p .= ' ' . $geometry->getM();
                    $this->measured = $this->measured || $geometry->isMeasured();
                }
                return $p;
            case Geometry::LINESTRING:
                foreach ($geometry->getComponents() as $component) {
                    $parts[] = $this->extractData($component);
                }
                return implode(', ', $parts);
            case Geometry::POLYGON:
            case Geometry::MULTI_POINT:
            case Geometry::MULTI_LINESTRING:
            case Geometry::MULTI_POLYGON:
                foreach ($geometry->getComponents() as $component) {
                    if ($component->isEmpty()) {
                        $parts[] = 'EMPTY';
                    } else {
                        $parts[] = '(' . $this->extractData($component) . ')';
                    }
                }
                return implode(', ', $parts);
            case Geometry::GEOMETRY_COLLECTION:
                foreach ($geometry->getComponents() as $component) {
                    $this->hasZ = $this->hasZ || $geometry->hasZ();
                    $this->measured = $this->measured || $geometry->isMeasured();

                    $extension = '';
                    if ($this->hasZ) {
                        $extension .= 'Z';
                    }
                    if ($this->measured) {
                        $extension .= 'M';
                    }
                    $data = $this->extractData($component);
                    $parts[] = strtoupper($component->geometryType())
                            . ($extension ? ' ' . $extension : '')
                            . ($data ? ' (' . $data . ')' : ' EMPTY');
                }
                return implode(', ', $parts);
        }
        return '';
    }
}
