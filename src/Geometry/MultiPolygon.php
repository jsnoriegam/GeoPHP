<?php
namespace GeoPHP\Geometry;

use GeoPHP\GeoPHP;

/**
 * MultiPolygon: A collection of Polygons
 *
 * @method   Polygon[] getComponents()
 * @property Polygon[] $components
 */
class MultiPolygon extends MultiSurface
{

    /**
     *
     * @param Polygon[] $components
     */
    public function __construct(array $components = [])
    {
        parent::__construct($components, true, Polygon::class);
    }

    /**
     * @return string "MultiPolygon"
     */
    public function geometryType(): string
    {
        return Geometry::MULTI_POLYGON;
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
            /** @var Point|null $geometry */
            $geometry = GeoPHP::geosToGeometry($geosObj->centroid());
            return $geometry !== null ? $geometry : new Point();
            // @codeCoverageIgnoreEnd
        }

        $x = 0;
        $y = 0;
        $totalArea = 0;
        foreach ($this->getComponents() as $component) {
            if ($component->isEmpty()) {
                continue;
            }
            $componentArea = $component->getArea();
            $totalArea += $componentArea;
            $componentCentroid = $component->getCentroid();
            $x += $componentCentroid->getX() * $componentArea;
            $y += $componentCentroid->getY() * $componentArea;
        }
        return new Point($x / $totalArea, $y / $totalArea);
    }

    /**
     * @return float
     */
    public function getArea(): float
    {
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return (float) $geosObj->area();
            // @codeCoverageIgnoreEnd
        }

        $area = 0;
        foreach ($this->components as $component) {
            $area += $component->getArea();
        }
        return (float) $area;
    }

    /**
     * @return Geometry LineString|MultiLineString
     */
    public function boundary(): Geometry
    {
        $rings = [];
        foreach ($this->getComponents() as $component) {
            $rings = array_merge($rings, $component->components);
        }
        return GeoPHP::geometryReduce(new MultiLineString($rings));
    }
}
