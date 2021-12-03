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

use geoPHP\Geometry\Collection;
use geoPHP\Geometry\Geometry;
use geoPHP\Geometry\Point;
use geoPHP\Geometry\LineString;
use geoPHP\Geometry\Polygon;

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
trait TWKBWriter
{

    /**
     * @var array{'decimalDigitsXY':int,'decimalDigitsZ':int,'decimalDigitsM':int,'includeSize':bool,'includeBoundingBoxes':bool,'hasM':bool,'hasZ':bool,'xyFactor':int|float,'zFactor':int|float,'mFactor':int|float}
     */
    protected $writeOptions = [
        'decimalDigitsXY' => 5,
        'decimalDigitsZ' => 0,
        'decimalDigitsM' => 0,
        'includeSize' => false,
        'includeBoundingBoxes' => false,
        'hasM' => false,
        'hasZ' => false,
        'xyFactor' => 1,
        'zFactor' => 1,
        'mFactor' => 1
    ];

    /**
     * @var BinaryWriter
     */
    protected $writer;

    /**
     * Serialize geometries into TWKB string.
     *
     * @return string The WKB string representation of the input geometries
     * @param Geometry $geometry The geometry
     * @param bool|true $writeAsHex Write the result in binary or hexadecimal system
     * @param int $decimalDigitsXY Coordinate precision of X and Y. Default is 5 decimals
     * @param int $decimalDigitsZ Coordinate precision of Z. Default is 0 decimal
     * @param int $decimalDigitsM Coordinate precision of M. Default is 0 decimal
     * @param bool $includeSizes Includes the size in bytes of the remainder of the geometry after the size attribute. Default is false
     * @param bool $includeBoundingBoxes Includes the coordinates of bounding box' two corner. Default is false
     *
     * @return string binary or hexadecimal representation of TWKB
     */
    public function write(
        Geometry $geometry,
        bool $writeAsHex = false,
        $decimalDigitsXY = null,
        $decimalDigitsZ = null,
        $decimalDigitsM = null,
        bool $includeSizes = false,
        bool $includeBoundingBoxes = false
    ): string {
        $this->writer = new BinaryWriter();

        $this->writeOptions['decimalDigitsXY'] = is_numeric($decimalDigitsXY) ? (int) $decimalDigitsXY : $this->writeOptions['decimalDigitsXY'];
        $this->writeOptions['decimalDigitsZ'] = is_numeric($decimalDigitsZ) ? (int) $decimalDigitsZ : $this->writeOptions['decimalDigitsZ'];
        $this->writeOptions['decimalDigitsM'] = is_numeric($decimalDigitsM) ? (int) $decimalDigitsM : $this->writeOptions['decimalDigitsM'];
        $this->writeOptions['includeSize'] = $includeSizes ? true : $this->writeOptions['includeSize'];
        $this->writeOptions['includeBoundingBoxes'] = $includeBoundingBoxes ? true : $this->writeOptions['includeBoundingBoxes'];
        $this->writeOptions['xyFactor'] = pow(10, $this->writeOptions['decimalDigitsXY']);
        $this->writeOptions['zFactor'] = pow(10, $this->writeOptions['decimalDigitsZ']);
        $this->writeOptions['mFactor'] = pow(10, $this->writeOptions['decimalDigitsM']);
        
        $twkb = $this->writeGeometry($geometry);
        
        return $writeAsHex ? current((array) unpack('H*', $twkb)) : $twkb;
    }

    /**
     * @param Geometry $geometry
     * @return string
     */
    protected function writeGeometry(Geometry $geometry): string
    {
        $this->writeOptions['hasZ'] = $geometry->hasZ();
        $this->writeOptions['hasM'] = $geometry->isMeasured();

        // Type and precision
        $type = self::$typeMap[$geometry->geometryType()] +
            (BinaryWriter::zigZagEncode($this->writeOptions['decimalDigitsXY']) << 4);
        $twkbHead = $this->writer->writeUInt8($type);

        // Is there extended precision information?
        $metadataHeader = $this->writeOptions['includeBoundingBoxes'] << 0;
        // Is there extended precision information?
        $metadataHeader += $this->writeOptions['includeSize'] << 1;
        // Is there an ID list?
        // TODO: implement this (needs metadata support in geoPHP)
        //$metadataHeader += $this->writeOptions['hasIdList'] << 2;
        // Is there extended precision information?
        $metadataHeader += ($geometry->hasZ() || $geometry->isMeasured()) << 3;
        // Is this an empty geometry?
        $metadataHeader += $geometry->isEmpty() << 4;

        $twkbHead .= $this->writer->writeUInt8($metadataHeader);

        $twkbGeom = '';
        if (!$geometry->isEmpty()) {
            $this->lastPoint = new Point(0, 0, 0, 0);

            switch ($geometry->geometryType()) {
                case Geometry::POINT:
                    /** @var Point $geometry */
                    $twkbGeom .= $this->writePoint($geometry);
                    break;
                case Geometry::LINESTRING:
                    /** @var LineString $geometry */
                    $twkbGeom .= $this->writeLineString($geometry);
                    break;
                case Geometry::POLYGON:
                    /** @var Polygon $geometry */
                    $twkbGeom .= $this->writePolygon($geometry);
                    break;
                case Geometry::MULTI_POINT:
                case Geometry::MULTI_LINESTRING:
                case Geometry::MULTI_POLYGON:
                case Geometry::GEOMETRY_COLLECTION:
                    /** @var Collection $geometry */
                    $twkbGeom .= $this->writeMulti($geometry);
                    break;
            }
        }

        if ($this->writeOptions['includeBoundingBoxes']) {
            $bBox = $geometry->getBoundingBox();
            
            if (!empty($bBox)) {
                // X
                $twkbBox = $this->writer->writeSVarInt($bBox['minx'] * $this->writeOptions['xyFactor']);
                $twkbBox .= $this->writer->writeSVarInt(($bBox['maxx'] - $bBox['minx']) * $this->writeOptions['xyFactor']);
                // Y
                $twkbBox .= $this->writer->writeSVarInt($bBox['miny'] * $this->writeOptions['xyFactor']);
                $twkbBox .= $this->writer->writeSVarInt(($bBox['maxy'] - $bBox['miny']) * $this->writeOptions['xyFactor']);
                if ($geometry->hasZ()) {
                    $bBox['minz'] = $geometry->minimumZ();
                    $bBox['maxz'] = $geometry->maximumZ();
                    $twkbBox .= $this->writer->writeSVarInt(round($bBox['minz'] * $this->writeOptions['zFactor']));
                    $twkbBox .= $this->writer->writeSVarInt(round(($bBox['maxz'] - $bBox['minz']) * $this->writeOptions['zFactor']));
                }
                if ($geometry->isMeasured()) {
                    $bBox['minm'] = $geometry->minimumM();
                    $bBox['maxm'] = $geometry->maximumM();
                    $twkbBox .= $this->writer->writeSVarInt($bBox['minm'] * $this->writeOptions['mFactor']);
                    $twkbBox .= $this->writer->writeSVarInt(($bBox['maxm'] - $bBox['minm']) * $this->writeOptions['mFactor']);
                }
                $twkbGeom = $twkbBox . $twkbGeom;
            }
        }

        if ($geometry->hasZ() || $geometry->isMeasured()) {
            $extendedPrecision = 0;
            if ($geometry->hasZ()) {
                $extendedPrecision |= ($geometry->hasZ() ? 0x1 : 0) | ($this->writeOptions['decimalDigitsZ'] << 2);
            }
            if ($geometry->isMeasured()) {
                $extendedPrecision |= ($geometry->isMeasured() ? 0x2 : 0) | ($this->writeOptions['decimalDigitsM'] << 5);
            }
            $twkbHead .= $this->writer->writeUInt8($extendedPrecision);
        }
        if ($this->writeOptions['includeSize']) {
            $twkbHead .= $this->writer->writeUVarInt(strlen($twkbGeom));
        }

        return $twkbHead . $twkbGeom;
    }

    /**
     * @param Point $geometry
     * @return string
     */
    protected function writePoint(Point $geometry): string
    {
        $x = round($geometry->getX() * $this->writeOptions['xyFactor']);
        $y = round($geometry->getY() * $this->writeOptions['xyFactor']);
        $z = round($geometry->getZ() * $this->writeOptions['zFactor']);
        $m = round($geometry->m() * $this->writeOptions['mFactor']);

        $twkb = $this->writer->writeSVarInt($x - $this->lastPoint->getX());
        $twkb .= $this->writer->writeSVarInt($y - $this->lastPoint->getY());
        if ($this->writeOptions['hasZ']) {
            $twkb .= $this->writer->writeSVarInt($z - $this->lastPoint->getZ());
        }
        if ($this->writeOptions['hasM']) {
            $twkb .= $this->writer->writeSVarInt($m - $this->lastPoint->m());
        }

        $this->lastPoint = new Point(
            $x,
            $y,
            $this->writeOptions['hasZ'] ? $z : null,
            $this->writeOptions['hasM'] ? $m : null
        );

        return $twkb;
    }

    /**
     * @param LineString $geometry
     * @return string
     */
    protected function writeLineString(LineString $geometry): string
    {
        $twkb = $this->writer->writeUVarInt($geometry->numPoints());
        foreach ($geometry->getComponents() as $component) {
            $twkb .= $this->writePoint($component);
        }
        return $twkb;
    }

    /**
     * @param Polygon $geometry
     * @return string
     */
    protected function writePolygon(Polygon $geometry): string
    {
        $twkb = $this->writer->writeUVarInt($geometry->numGeometries());
        foreach ($geometry->getComponents() as $component) {
            $twkb .= $this->writeLineString($component);
        }
        
        return $twkb;
    }

    /**
     * @param Collection $geometry
     * @return string
     */
    protected function writeMulti(Collection $geometry): string
    {
        $twkb = $this->writer->writeUVarInt($geometry->numGeometries());
        //if ($geometry->hasIdList()) {
        //  foreach ($geometry->getComponents() as $component) {
        //      $this->writer->writeUVarInt($component->getId());
        //  }
        //}
        foreach ($geometry->getComponents() as $component) {
            if ($geometry->geometryType() !== Geometry::GEOMETRY_COLLECTION) {
                $func = 'write' . $component->geometryType();
                $twkb .= $this->$func($component);
            } else {
                $twkb .= $this->writeGeometry($component);
            }
        }
        
        return $twkb;
    }
}
