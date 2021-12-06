<?php

namespace geoPHP\Geometry;

use geoPHP\Exception\InvalidGeometryException;

/**
 * A curve consists of a sequence of Points.
 *
 * @package GeoPHPGeometry
 */
abstract class Curve extends Collection
{

    /**
     * @var Point
     */
    protected $startPoint;

    /**
     * @var Point
     */
    protected $endPoint;

    public function __construct(
        array $components = [],
        bool $allowEmptyComponents = false,
        string $allowedComponentType = Point::class
    ) {
        parent::__construct($components, $allowEmptyComponents, $allowedComponentType);

        if (count($this->components) === 1) {
            throw new InvalidGeometryException("Cannot construct a " . static::class . " with a single point");
        }
    }

    /**
     * @return string "Curve"
     */
    public function geometryType(): string
    {
        return Geometry::CURVE;
    }

    /**
     * @return int 1
     */
    public function dimension(): int
    {
        return 1;
    }

    /**
     * @return Point
     */
    public function startPoint()
    {
        if (!isset($this->startPoint)) {
            $this->startPoint = $this->pointN(1);
        }
        return $this->startPoint;
    }

    /**
     * @return Point
     */
    public function endPoint()
    {
        if (!isset($this->endPoint)) {
            $this->endPoint = $this->pointN($this->numPoints());
        }
        return $this->endPoint;
    }

    /**
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->startPoint()->equals($this->endPoint());
    }

    /**
     * @return bool
     */
    public function isRing(): bool
    {
        return ($this->isClosed() && $this->isSimple());
    }
    
    /**
     * Tests whether this geometry is simple.
     * According to the OGR specification a ring is only simple if its start and end point are equal (in all values).
     * Currently, neither GEOS, nor PostGIS support it.
     *
     * @return bool
     */
    public function isSimple(): bool
    {
        return $this->startPoint()->equals($this->endPoint());
    }
    
    /**
     * @return bool
     */
    public function isValid(): bool
    {
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $geosObj->checkValidity()['valid'];
        }
        return $this->isSimple();
    }
    
    /**
     * The boundary of a non-closed Curve consists of its end Points.
     *
     * @return LineString|MultiPoint
     */
    public function boundary(): Geometry
    {
        return $this->isEmpty() ?
            new LineString() :
            ($this->isClosed() ?
                new MultiPoint() :
                new MultiPoint([$this->startPoint() ?? new Point(), $this->endPoint() ?? new Point()])
            );
    }

    /**
     * Not valid for this geometry type
     *
     * @return float 0.0
     */
    public function getArea(): float
    {
        return 0.0;
    }
}
