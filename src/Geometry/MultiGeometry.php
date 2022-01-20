<?php

namespace GeoPHP\Geometry;

use GeoPHP\GeoPHP;

/**
 * MultiGeometry is an abstract collection of geometries
 *
 * @package GeoPHPGeometry
 */
abstract class MultiGeometry extends Collection
{

    /**
     * @param Geometry[] $components
     * @param bool       $allowEmptyComponents
     * @param string     $allowedComponentType
     */
    public function __construct(
        array $components = [],
        bool $allowEmptyComponents = true,
        string $allowedComponentType = Geometry::class
    ) {
        parent::__construct($components, $allowEmptyComponents, $allowedComponentType);
    }

    /**
     * @return bool
     */
    public function isSimple(): bool
    {
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $geosObj->isSimple();
            // @codeCoverageIgnoreEnd
        }

        // A collection is simple if all it's components are simple
        foreach ($this->components as $component) {
            if (!$component->isSimple()) {
                return false;
            }
        }

        return true;
    }
    
    /**
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->getGeos()) {
            return parent::isValid();
        }

        // A collection is valid if all it's components are valid
        foreach ($this->components as $component) {
            if (!$component->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the boundary, or an empty geometry of appropriate dimension if this Geometry is empty.
     * By default, the boundary of a collection is the boundary of it's components.
     * In the case of zero-dimensional geometries, an empty GeometryCollection is returned.
     *
     * @return Geometry|GeometryCollection
     */
    public function boundary(): Geometry
    {
        if ($this->isEmpty()) {
            return new GeometryCollection();
        }

        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            /** @phpstan-ignore-next-line */
            return $geosObj->boundary();
            // @codeCoverageIgnoreEnd
        }

        $componentsBoundaries = [];
        foreach ($this->components as $component) {
            $componentsBoundaries[] = $component->boundary();
        }
        return GeoPHP::buildGeometry($componentsBoundaries);
    }

    /**
     * Returns the total area of this collection.
     *
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

        $area = 0.0;
        foreach ($this->components as $component) {
            $area += $component->getArea();
        }
        return (float) $area;
    }

    /**
     * Returns the length of this Collection in its associated spatial reference.
     * Eg. if Geometry is in geographical coordinate system it returns the length in degrees
     *
     * @return float
     */
    public function getLength(): float
    {
        $length = 0.0;
        foreach ($this->components as $component) {
            $length += $component->getLength();
        }
        return $length;
    }

    public function length3D(): float
    {
        $length = 0.0;
        foreach ($this->components as $component) {
            $length += $component->length3D();
        }
        return $length;
    }

    /**
     * Returns the degree based Geometry' length in meters
     *
     * @param  float|int $radius Default is the semi-major axis of WGS84.
     * @return float the length in meters
     */
    public function greatCircleLength($radius = GeoPHP::EARTH_WGS84_SEMI_MAJOR_AXIS): float
    {
        $length = 0.0;
        foreach ($this->components as $component) {
            $length += $component->greatCircleLength($radius);
        }
        return $length;
    }

    /**
     * @return float sum haversine length of all components
     */
    public function haversineLength(): float
    {
        $length = 0.0;
        foreach ($this->components as $component) {
            $length += $component->haversineLength();
        }
        return $length;
    }

    public function minimumZ()
    {
        $min = PHP_INT_MAX;
        foreach ($this->components as $component) {
            $componentMin = $component->minimumZ();
            if (null !== $componentMin && $componentMin < $min) {
                $min = $componentMin;
            }
        }
        return $min !== PHP_INT_MAX ? $min : null;
    }

    public function maximumZ()
    {
        $max = PHP_INT_MIN;
        foreach ($this->components as $component) {
            $componentMax = $component->maximumZ();
            if ($componentMax > $max) {
                $max = $componentMax;
            }
        }
        return $max !== PHP_INT_MIN ? $max : null;
    }

    public function zDifference()
    {
        $startPoint = $this->startPoint();
        $endPoint = $this->endPoint();
        if ($startPoint && $endPoint && $startPoint->hasZ() && $endPoint->hasZ()) {
            return abs($startPoint->getZ() - $endPoint->getZ());
        }
        
        return null;
    }

    /**
     *
     * @param int|float $verticalTolerance
     * @return int|float|null
     */
    public function elevationGain($verticalTolerance = 0)
    {
        $gain = null;
        foreach ($this->components as $component) {
            $gain += $component->elevationGain($verticalTolerance);
        }
        return $gain;
    }

    public function elevationLoss($verticalTolerance = 0)
    {
        $loss = null;
        foreach ($this->components as $component) {
            $loss += $component->elevationLoss($verticalTolerance);
        }
        return $loss;
    }

    public function minimumM()
    {
        $min = PHP_INT_MAX;
        foreach ($this->components as $component) {
            $componentMin = $component->minimumM();
            if ($componentMin < $min) {
                $min = $componentMin;
            }
        }
        return $min !== PHP_INT_MAX ? $min : null;
    }

    public function maximumM()
    {
        $max = PHP_INT_MIN;
        foreach ($this->components as $component) {
            $componentMax = $component->maximumM();
            if ($componentMax > $max) {
                $max = $componentMax;
            }
        }
        return $max !== PHP_INT_MIN ? $max : null;
    }

    public function isClosed(): bool
    {
        return true;
    }
}
