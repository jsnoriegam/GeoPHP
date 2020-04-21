<?php
namespace geoPHP\Geometry;

use geoPHP\geoPHP;
use geoPHP\Exception\UnsupportedMethodException;

/**
 * A MultiPoint is a 0-dimensional Collection.
 * The elements of a MultiPoint are restricted to Points.
 * The Points are not connected or ordered in any semantically important way.
 * A MultiPoint is simple if no two Points in the MultiPoint are equal (have identical coordinate values in X and Y).
 * Every MultiPoint is spatially equal under the definition in OGC 06-103r4 Clause 6.1.15.3 to a simple Multipoint.
 *
 * @package GeoPHPGeometry
 */
class MultiPoint extends MultiGeometry
{

    public function __construct(array $components = [])
    {
        parent::__construct($components, true, Point::class);
    }

    /**
     * @return string "MultiPoint"
     */
    public function geometryType(): string
    {
        return Geometry::MULTI_POINT;
    }

    /**
     * MultiPoint is 0-dimensional
     *
     * @return int 0
     */
    public function dimension(): int
    {
        return 0;
    }

    /**
     * @param  array $array
     * @return MultiPoint
     */
    public static function fromArray(array $array): MultiPoint
    {
        $points = [];
        foreach ($array as $point) {
            $points[] = Point::fromArray($point);
        }
        return new MultiPoint($points);
    }

    /**
     * A MultiPoint is simple if no points inside are equal (have identical coordinate values in X and Y).
     *
     * @return bool
     */
    public function isSimple(): bool
    {
        $componentCount = count($this->components);
        for ($i = 0; $i < $componentCount; ++$i) {
            for ($j = $i + 1; $j < $componentCount; ++$j) {
                if ($this->components[$i]->equals($this->components[$j])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * The boundary of a MultiPoint is the empty set.
     *
     * @return GeometryCollection GeometryCollection EMPTY
     */
    public function boundary(): Geometry
    {
        return new GeometryCollection();
    }

    /**
     * @return int
     */
    public function numPoints(): int
    {
        return $this->numGeometries();
    }

    /**
     * @return Point
     */
    public function getCentroid(): Point
    {
        if ($this->isEmpty()) {
            return new Point();
        }

        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return geoPHP::geosToGeometry($this->getGeos()->centroid());
            // @codeCoverageIgnoreEnd
        }

        $x = 0;
        $y = 0;
        foreach ($this->getComponents() as $component) {
            $x += $component->getX();
            $y += $component->getY();
        }
        return new Point($x / $this->numPoints(), $y / $this->numPoints());
    }

    /**
     * Not valid for this geometry type
     *
     * @param  bool|false $toArray
     * @throws UnsupportedMethodException
     */
    public function explode(bool $toArray = false): array
    {
        throw new UnsupportedMethodException(
            __METHOD__,
            null,
            "A " . __CLASS__ . " does not support the method '" . __METHOD__  . "'."
        );
    }
}
