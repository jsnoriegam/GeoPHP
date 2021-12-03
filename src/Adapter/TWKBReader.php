<?php
/**
 * This file contains the BinaryReader class.
 * For more information see the class description below.
 *
 * @author Peter Bathory <peter.bathory@cartographia.hu>
 * @since 2016-02-18
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace geoPHP\Adapter;

use geoPHP\Geometry\Geometry;
use geoPHP\Geometry\GeometryCollection;
use geoPHP\Geometry\Point;
use geoPHP\Geometry\MultiPoint;
use geoPHP\Geometry\LineString;
use geoPHP\Geometry\MultiLineString;
use geoPHP\Geometry\Polygon;
use geoPHP\Geometry\MultiPolygon;
use geoPHP\Geometry\MultiGeometry;

/**
 * PHP Geometry <-> TWKB encoder/decoder
 *
 * "Tiny Well-known Binary is is a multi-purpose format for serializing vector geometry data into a byte buffer,
 * with an emphasis on minimizing size of the buffer."
 * @see https://github.com/TWKB/Specification/blob/master/twkb.md
 *
 * This implementation supports:
 * - reading and writing all geometry types (1-7)
 * - empty geometries
 * - extended precision (Z, M coordinates; custom precision)
 * Partially supports:
 * - bounding box: can read and write, but don't store readed boxes (API missing)
 * - size attribute: can read and write size attribute, but seeking is not supported
 * - ID list: can read and write, but API is completely missing
 * 
 * @property Point $lastPoint
 */
trait TWKBReader
{

    /**
     * @var BinaryReader
     */
    protected $reader;

    /**
     * Read TWKB into geometry objects
     *
     * @param string $twkb Tiny Well-known-binary string
     * @param bool $isHexString If this is a hexadecimal string that is in need of packing
     * @return Geometry
     * @throws \Exception
     */
    public function read(string $twkb, bool $isHexString = false): Geometry
    {
        if ($isHexString) {
            $twkb = pack('H*', $twkb);
        }

        if (empty($twkb)) {
            throw new \Exception('Cannot read empty TWKB. Found ' . gettype($twkb));
        }

        $this->reader = new BinaryReader($twkb);
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
        $options = [];
        $type = $this->reader->readUInt8();
        $metadataHeader = $this->reader->readUInt8();

        $geometryType = $type & 0x0F;
        $options['precision'] = BinaryReader::zigZagDecode($type >> 4);
        $options['precisionFactor'] = pow(10, $options['precision']);

        $options['hasBoundingBox'] = ($metadataHeader >> 0 & 1) == 1;
        $options['hasSizeAttribute'] = ($metadataHeader >> 1 & 1) == 1;
        $options['hasIdList'] = ($metadataHeader >> 2 & 1) == 1;
        $options['hasExtendedPrecision'] = ($metadataHeader >> 3 & 1) == 1;
        $options['isEmpty'] = ($metadataHeader >> 4 & 1) == 1;
        $unused1 = ($metadataHeader >> 5 & 1) == 1;
        $unused2 = ($metadataHeader >> 6 & 1) == 1;
        $unused3 = ($metadataHeader >> 7 & 1) == 1;

        if ($options['hasExtendedPrecision']) {
            $extendedPrecision = $this->reader->readUInt8();

            $options['hasZ'] = ($extendedPrecision & 0x01) === 0x01;
            $options['hasM'] = ($extendedPrecision & 0x02) === 0x02;

            $options['zPrecision'] = ($extendedPrecision & 0x1C) >> 2;
            $options['zPrecisionFactor'] = pow(10, $options['zPrecision']);

            $options['mPrecision'] = ($extendedPrecision & 0xE0) >> 5;
            $options['mPrecisionFactor'] = pow(10, $options['mPrecision']);
        } else {
            $options['hasZ'] = false;
            $options['hasM'] = false;
            $options['zPrecisionFactor'] = 0;
            $options['mPrecisionFactor'] = 0;
        }
        if ($options['hasSizeAttribute']) {
            $options['remainderSize'] = $this->reader->readUVarInt();
        }
        if ($options['hasBoundingBox']) {
            $dimension = 2 + ($options['hasZ'] ? 1 : 0) + ($options['hasM'] ? 1 : 0);
            $precisions = [
                $options['precisionFactor'],
                $options['precisionFactor'],
                $options['hasZ'] ? $options['zPrecisionFactor'] : 0,
                $options['hasM'] ? $options['mPrecisionFactor'] : 0
            ];

            $bBoxMin = $bBoxMax = [];
            for ($i = 0; $i < $dimension; $i++) {
                $bBoxMin[$i] = $this->reader->readUVarInt() / $precisions[$i];
                $bBoxMax[$i] = $this->reader->readUVarInt() / $precisions[$i] + $bBoxMin[$i];
            }
            /** @noinspection PhpUndefinedVariableInspection (minimum 2 dimension) */
            $options['boundingBox'] = ['minXYZM' => $bBoxMin, 'maxXYZM' => $bBoxMax];
        }

        if ($unused1) {
            $this->reader->readUVarInt();
        }
        if ($unused2) {
            $this->reader->readUVarInt();
        }
        if ($unused3) {
            $this->reader->readUVarInt();
        }

        $this->lastPoint = new Point(0, 0, 0, 0);

        switch ($geometryType) {
            case 1:
                return $this->getPoint($options);
            case 2:
                return $this->getLineString($options);
            case 3:
                return $this->getPolygon($options);
            case 4:
                return $this->getMulti('Point', $options);
            case 5:
                return $this->getMulti('LineString', $options);
            case 6:
                return $this->getMulti('Polygon', $options);
            case 7:
                return $this->getMulti('Geometry', $options);
            default:
                throw new \Exception(
                    'Geometry type ' . $geometryType .
                        ' (' . (self::$typeMap[$geometryType] ?? 'unknown') . ') not supported'
                );
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return Point
     * @throws \Exception
     */
    protected function getPoint(array $options): Point
    {
        if ($options['isEmpty']) {
            return new Point();
        }
        $x = round(
            $this->lastPoint->getX() + $this->reader->readSVarInt() / $options['precisionFactor'],
            $options['precision']
        );
        $y = round(
            $this->lastPoint->getY() + $this->reader->readSVarInt() / $options['precisionFactor'],
            $options['precision']
        );
        $z = $options['hasZ'] ? round(
            $this->lastPoint->getZ() + $this->reader->readSVarInt() / $options['zPrecisionFactor'],
            $options['zPrecision']
        ) : null;
        $m = $options['hasM'] ? round(
            $this->lastPoint->m() + $this->reader->readSVarInt() / $options['mPrecisionFactor'],
            $options['mprecision']
        ) : null;

        $this->lastPoint = new Point($x, $y, $z, $m);
        
        return $this->lastPoint;
    }

    /**
     * @param array<string, mixed> $options
     * @return LineString
     * @throws \Exception
     */
    protected function getLineString(array $options)
    {
        if ($options['isEmpty']) {
            return new LineString();
        }

        $numPoints = $this->reader->readUVarInt();

        $points = [];
        for ($i = 0; $i < $numPoints; $i++) {
            $points[] = $this->getPoint($options);
        }

        return new LineString($points);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return Polygon
     * @throws \Exception
     */
    protected function getPolygon(array $options)
    {
        if ($options['isEmpty']) {
            return new Polygon();
        }

        $ringCount = $this->reader->readUVarInt();

        $rings = [];
        for ($i = 0; $i < $ringCount; $i++) {
            $rings[] = $this->getLineString($options);
        }

        return new Polygon($rings, true);
    }

    /**
     * @param string $type
     * @param array<string, mixed> $options
     * @return MultiGeometry
     * @throws \Exception
     */
    private function getMulti(string $type, array $options): Geometry
    {
        $multiLength = $this->reader->readUVarInt();

        if ($options['hasIdList']) {
            $idList = [];
            for ($i = 0; $i < $multiLength; ++$i) {
                $idList[] = $this->reader->readSVarInt();
            }
        }
        
        $components = [];
        for ($i = 0; $i < $multiLength; $i++) {
            if ($type !== 'Geometry') {
                $func = 'get' . $type;
                $components[] = $this->$func($options);
            } else {
                $components[] = $this->getGeometry();
            }
        }

        switch ($type) {
            case 'Point':
                return new MultiPoint($components);
            case 'LineString':
                return new MultiLineString($components);
            case 'Polygon':
                return new MultiPolygon($components);
        }
        
        return new GeometryCollection($components);
    }
}
