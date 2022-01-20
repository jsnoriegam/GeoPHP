<?php
namespace GeoPHP\Adapter;

use GeoPHP\Geometry\Geometry;

/**
 * EWKB (Extended Well Known Binary) Adapter
 */
class EWKB extends WKB
{

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
        $this->SRID = $geometry->getSRID();
        $this->hasSRID = $this->SRID !== null;
        return parent::write($geometry, $writeAsHex, $bigEndian);
    }

    /**
     * @param Geometry $type
     * @param bool $writeSRID default false
     * @return string
     */
    protected function writeType(Geometry $type, bool $writeSRID = false): string
    {
        return parent::writeType($type, true);
    }
}
