<?php
namespace geoPHP\Adapter;

use geoPHP\Geometry\Geometry;

/**
 * EWKT (Extended Well Known Text) Adapter
 */
class EWKT extends WKT
{

    /**
     * Serialize geometries into an EWKT string.
     *
     * @param Geometry $geometry
     * @return string The Extended-WKT string representation of the input geometries
     */
    public function write(Geometry $geometry): string
    {
        $srid = $geometry->getSRID();
        
        return ($srid ? 'SRID=' . $srid . ';' : '') . $geometry->out('wkt');
    }
}
