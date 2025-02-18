<?php
namespace GeoPHP\Adapter;

use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\GeometryCollection;
use GeoPHP\Geometry\Point;
use GeoPHP\Geometry\MultiPoint;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\MultiLineString;
use GeoPHP\Geometry\Polygon;
use GeoPHP\Geometry\MultiPolygon;
use GeoPHP\Exception\InvalidGeometryException;

/*
 * (c) Patrick Hayes
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Geometry/WKB encoder/decoder
 * Reader can decode EWKB too. Writer always encodes valid WKBs
 *
 */
class WKB implements GeoAdapter
{

    const Z_MASK = 0x80000000;
    const M_MASK = 0x40000000;
    const SRID_MASK = 0x20000000;
    const WKB_XDR = 1;
    const WKB_NDR = 0;

    /**
     * @var bool $hasZ
     */
    protected $hasZ = false;
    
    /**
     * @var bool $hasM
     */
    protected $hasM = false;
    
    /**
     * @var bool $hasSRID
     */
    protected $hasSRID = false;
    
    /**
     * @var int $SRID
     */
    protected $SRID;
    
    /**
     * @var int $dimension
     */
    protected $dimension = 2;

    /**
     * @var BinaryReader $reader
     */
    protected $reader;

    /**
     * @var BinaryWriter $writer
     */
    protected $writer;

    /**
     * @var array<int> Maps Geometry types to WKB type codes
     */
    public static $typeMap = [
        Geometry::POINT => 1,
        Geometry::LINESTRING => 2,
        Geometry::POLYGON => 3,
        Geometry::MULTI_POINT => 4,
        Geometry::MULTI_LINESTRING => 5,
        Geometry::MULTI_POLYGON => 6,
        Geometry::GEOMETRY_COLLECTION => 7,
        //Not supported types:
        Geometry::CIRCULAR_STRING => 8,
        Geometry::COMPOUND_CURVE => 9,
        Geometry::CURVE_POLYGON => 10,
        Geometry::MULTI_CURVE => 11,
        Geometry::MULTI_SURFACE => 12,
        Geometry::CURVE => 13,
        Geometry::SURFACE => 14,
        Geometry::POLYHEDRAL_SURFACE => 15,
        Geometry::TIN => 16,
        Geometry::TRIANGLE => 17
    ];

    /**
     * Read WKB into geometry objects
     *
     * @param string $wkb Well-known-binary string
     * @param bool $isHexString If this is a hexadecimal string that is in need of packing
     * @return Geometry
     * @throws \Exception
     */
    public function read(string $wkb, bool $isHexString = false): Geometry
    {
        if ($isHexString) {
            $wkb = pack('H*', $wkb);
        }

        if (empty($wkb)) {
            throw new \Exception('Cannot read empty WKB geometry. Found ' . gettype($wkb));
        }

        $this->reader = new BinaryReader($wkb);
        $geometry = $this->getGeometry();
        $this->reader->close();

        return $geometry;
    }

    /**
     * @return Geometry
     * @throws \Exception
     */
    protected function getGeometry(): Geometry
    {
        $this->hasZ = false;
        $this->hasM = false;
        $SRID = null;

        $this->reader->setEndianness(
            $this->reader->readSInt8() === self::WKB_XDR ? BinaryReader::LITTLE_ENDIAN : BinaryReader::BIG_ENDIAN
        );

        $wkbType = $this->reader->readUInt32();

        if (($wkbType & $this::SRID_MASK) === $this::SRID_MASK) {
            $SRID = $this->reader->readUInt32();
        }
        $geometryType = null;
        if ($wkbType >= 1000 && $wkbType < 2000) {
            $this->hasZ = true;
            $geometryType = $wkbType - 1000;
        } elseif ($wkbType >= 2000 && $wkbType < 3000) {
            $this->hasM = true;
            $geometryType = $wkbType - 2000;
        } elseif ($wkbType >= 3000 && $wkbType < 4000) {
            $this->hasZ = true;
            $this->hasM = true;
            $geometryType = $wkbType - 3000;
        }

        if ($wkbType & $this::Z_MASK) {
            $this->hasZ = true;
        }
        if ($wkbType & $this::M_MASK) {
            $this->hasM = true;
        }
        $this->dimension = 2 + ($this->hasZ ? 1 : 0) + ($this->hasM ? 1 : 0);

        if ($geometryType === null) {
            $geometryType = $wkbType & 0xF; // remove any masks from type
        }
        $geometry = null;
        switch ($geometryType) {
            case 1:
                $geometry = $this->getPoint();
                break;
            case 2:
                $geometry = $this->getLineString();
                break;
            case 3:
                $geometry = $this->getPolygon();
                break;
            case 4:
                $geometry = $this->getMulti('Point');
                break;
            case 5:
                $geometry = $this->getMulti('LineString');
                break;
            case 6:
                $geometry = $this->getMulti('Polygon');
                break;
            case 7:
                $geometry = $this->getMulti('Geometry');
                break;
            default:
                throw new \Exception(
                    'Geometry type ' . $geometryType .
                    ' (' . (self::$typeMap[$geometryType] ?? 'unknown') . ') not supported'
                );
        }
        if ($SRID !== null) {
            $geometry->setSRID($SRID);
        }
        
        return $geometry;
    }

    /**
     * @return Point
     */
    protected function getPoint(): Point
    {
        $coordinates = $this->reader->readDoubles($this->dimension * 8);
        
        switch (count($coordinates)) {
            case 2:
                return new Point($coordinates[0], $coordinates[1]);
            case 3:
                return $this->hasZ ?
                    new Point($coordinates[0], $coordinates[1], $coordinates[2]) :
                    new Point($coordinates[0], $coordinates[1], null, $coordinates[2]);
            case 4:
                return new Point($coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3]);
        }
        
        return new Point;
    }

    /**
     * @return LineString
     */
    protected function getLineString(): LineString
    {
        // Get the number of points expected in this string out of the first 4 bytes
        $lineLength = $this->reader->readUInt32();

        // Return an empty linestring if there is no line-length
        if ($lineLength === null) {
            return new LineString();
        }

        $components = [];
        for ($i = 0; $i < $lineLength; ++$i) {
            $components[] = $this->getPoint();
        }
        
        return new LineString($components);
    }

    /**
     * @return Polygon
     */
    protected function getPolygon(): Polygon
    {
        // Get the number of linestring expected in this poly out of the first 4 bytes
        $polyLength = $this->reader->readUInt32();

        $components = [];
        $i = 1;
        while ($i <= $polyLength) {
            $ring = $this->getLineString();
            if (!$ring->isEmpty()) {
                $components[] = $ring;
            }
            $i++;
        }

        return new Polygon($components);
    }

    /**
     * @param string $type
     * @return MultiPoint|MultiLineString|MultiPolygon|GeometryCollection
     */
    private function getMulti(string $type): Geometry
    {
        // Get the number of items expected in this multi out of the first 4 bytes
        $multiLength = $this->reader->readUInt32();

        $components = [];
        for ($i = 0; $i < $multiLength; ++$i) {
            $component = $this->getGeometry();
            $component->setSRID(null);
            $components[] = $component;
        }
        
        switch ($type) {
            case 'Point':
                /** @var Point[] $components */
                return new MultiPoint($components);
            case 'LineString':
                /** @var LineString[] $components */
                return new MultiLineString($components);
            case 'Polygon':
                /** @var Polygon[] $components */
                return new MultiPolygon($components);
        }
        
        return new GeometryCollection($components);
    }

    /**
     * Serialize geometries into WKB string.
     *
     * @param Geometry $geometry The geometry
     * @param bool $writeAsHex Write the result in binary or hexadecimal system. Default false.
     * @param bool $bigEndian Write in BigEndian byte order. Default false.
     *
     * @return string The WKB string representation of the input geometries
     */
    public function write(Geometry $geometry, bool $writeAsHex = false, bool $bigEndian = false): string
    {
        $this->writer = new BinaryWriter($bigEndian ? BinaryWriter::BIG_ENDIAN : BinaryWriter::LITTLE_ENDIAN);
        $wkb = $this->writeGeometry($geometry);

        $data = unpack('H*', $wkb);
        return $writeAsHex ? ($data ? current($data) : '') : $wkb;
    }

    /**
     * @param Geometry $geometry
     * @return string
     */
    protected function writeGeometry(Geometry $geometry): string
    {
        $this->hasZ = $geometry->hasZ();
        $this->hasM = $geometry->isMeasured();

        $wkb = $this->writer->writeSInt8($this->writer->isBigEndian() ? self::WKB_NDR : self::WKB_XDR);
        $wkb .= $this->writeType($geometry);
        
        switch ($geometry->geometryType()) {
            case Geometry::POINT:
                /** @var Point $geometry */
                $wkb .= $this->writePoint($geometry);
                break;
            case Geometry::LINESTRING:
                /** @var LineString $geometry */
                $wkb .= $this->writeLineString($geometry);
                break;
            case Geometry::POLYGON:
                /** @var Polygon $geometry */
                $wkb .= $this->writePolygon($geometry);
                break;
            case Geometry::MULTI_POINT:
                /** @var MultiPoint $geometry */
                $wkb .= $this->writeMulti($geometry);
                break;
            case Geometry::MULTI_LINESTRING:
                /** @var MultiLineString $geometry */
                $wkb .= $this->writeMulti($geometry);
                break;
            case Geometry::MULTI_POLYGON:
                /** @var MultiPolygon $geometry */
                $wkb .= $this->writeMulti($geometry);
                break;
            case Geometry::GEOMETRY_COLLECTION:
                /** @var GeometryCollection $geometry */
                $wkb .= $this->writeMulti($geometry);
                break;
        }
        
        return $wkb;
    }

    /**
     * @param Point $point
     * @return string
     * @throws InvalidGeometryException
     */
    protected function writePoint(Point $point): string
    {
        if ($point->isEmpty()) {
            #return $this->writer->writeDouble(null) . $this->writer->writeDouble(null);
            
            // GEOS throws an IllegalArgumentException with "Empty Points cannot be represented in WKB."
            throw new InvalidGeometryException("Empty Points cannot be represented in WKB");
        }
        $wkb = $this->writer->writeDouble($point->getX()) . $this->writer->writeDouble($point->getY());

        if ($this->hasZ) {
            $wkb .= $this->writer->writeDouble($point->getZ());
        }
        if ($this->hasM) {
            $wkb .= $this->writer->writeDouble($point->m());
        }
        
        return $wkb;
    }

    /**
     * @param LineString $line
     * @return string
     */
    protected function writeLineString(LineString $line): string
    {
        // Set the number of points in this line
        $wkb = $this->writer->writeUInt32($line->numPoints());

        // Set the coords
        foreach ($line->getComponents() as $point) {
            $wkb .= $this->writePoint($point);
        }

        return $wkb;
    }

    /**
     * @param Polygon $poly
     * @return string
     */
    protected function writePolygon(Polygon $poly): string
    {
        // Set the number of lines in this poly
        $wkb = $this->writer->writeUInt32($poly->numGeometries());

        // Write the lines
        foreach ($poly->getComponents() as $line) {
            $wkb .= $this->writeLineString($line);
        }

        return $wkb;
    }

    /**
     * @param MultiPoint|MultiPolygon|MultiLineString|GeometryCollection $geometry
     * @return string
     */
    protected function writeMulti(Geometry $geometry): string
    {
        // Set the number of components
        $wkb = $this->writer->writeUInt32($geometry->numGeometries());

        // Write the components
        foreach ($geometry->getComponents() as $component) {
            $wkb .= $this->writeGeometry($component);
        }

        return $wkb;
    }

    /**
     * @param Geometry $geometry
     * @param bool $writeSRID default false
     * @return string
     */
    protected function writeType(Geometry $geometry, bool $writeSRID = false): string
    {
        $type = self::$typeMap[$geometry->geometryType()];
        
        // Binary OR to mix in additional properties
        if ($this->hasZ) {
            $type = $type | $this::Z_MASK;
        }
        if ($this->hasM) {
            $type = $type | $this::M_MASK;
        }
        if ($writeSRID && $geometry->getSRID()) {
            $type = $type | $this::SRID_MASK;
        }
        
        return $this->writer->writeUInt32($type) .
            ($writeSRID && $geometry->getSRID() ? $this->writer->writeUInt32($this->SRID) : '');
    }
}
