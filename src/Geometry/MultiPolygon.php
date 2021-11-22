<?php
namespace geoPHP\Geometry;

use geoPHP\geoPHP;

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

        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            /** @phpstan-ignore-next-line */
            return geoPHP::geosToGeometry($this->getGeos()->centroid());
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
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return (float) $this->getGeos()->area();
            // @codeCoverageIgnoreEnd
        }

        $area = 0;
        foreach ($this->components as $component) {
            $area += $component->getArea();
        }
        return (float) $area;
    }

    /**
     * @return LineString|MultiLineString
     */
    public function boundary(): Geometry
    {
        $rings = [];
        foreach ($this->getComponents() as $component) {
            $rings = array_merge($rings, $component->components);
        }
        return geoPHP::geometryReduce(new MultiLineString($rings));
    }
}
