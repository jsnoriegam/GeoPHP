<?php

namespace geoPHP\Geometry;

use geoPHP\geoPHP;

/**
 * MultiLineString: A collection of LineStrings
 *
 * @package GeoPHPGeometry
 * @method  LineString[] getComponents()
 */
class MultiLineString extends MultiCurve
{

    public function __construct(array $components = [])
    {
        parent::__construct($components, true, LineString::class);
    }

    /**
     * @var LineString[] The elements of a MultiLineString are LineStrings
     */
    protected $components = [];

    /**
     * @return string "MultiLineString"
     */
    public function geometryType(): string
    {
        return Geometry::MULTI_LINESTRING;
    }

    /**
     * @return Point
     */
    public function getCentroid(): Point
    {
        if ($this->isEmpty()) {
            return new Point;
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
        $totalLength = 0;
        $componentLength = 0;
        $components = $this->getComponents();
        foreach ($components as $line) {
            if ($line->isEmpty()) {
                continue;
            }
            $componentLength = $line->getLength();
            $componentCentroid = $line->getCentroid();
            $x += $componentCentroid->getX() * $componentLength;
            $y += $componentCentroid->getY() * $componentLength;
            $totalLength += $componentLength;
        }
        if ($totalLength == 0) {
            return $this->getPoints()[0];
        }
        return new Point($x / $totalLength, $y / $totalLength);
    }

    /**
     * The boundary of a MultiLineString is a MultiPoint which consists of the start and
     * end points of its non-closed LineStrings.
     *
     * @internal That seems not to be the full truth. When z-values come in, all gets a little confusing.
     *          PostGIS: "MULTILINESTRING((0 0 0, 1 0 0),(0 0 0, 1 0 1))" => "MULTIPOINT EMPTY"
     *          PostGIS: "MULTILINESTRING((0 0 0, 1 0 0),(0 0 0, 1 1 1))" => "MULTIPOINT(1 0 0,1 1 1)"
     *          PostGIS: "MULTILINESTRING((1 1 1, -1 1 1),(1 1 1,-1 1 0.5, 1 1 0.5))" => "MULTIPOINT(-1 1 1,1 1 0.75)"
     *          PostGIS: "MULTILINESTRING((0 0 0, -1 1 1),(0 0 0,-1 1 0.5, 1 1 0.5))" => "MULTIPOINT(-1 1 1,1 1 0.5)"
     *
     * @return MultiPoint
     */
    public function boundary(): Geometry
    {
        $points = [];
        foreach ($this->components as $line) {
            if (!$line->isEmpty() && !$line->isClosed()) {
                $points[] = $line->startPoint();
                $points[] = $line->endPoint();
            }
        }
        return new MultiPoint($points);
    }
}
