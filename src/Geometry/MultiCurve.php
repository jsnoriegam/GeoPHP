<?php

namespace GeoPHP\Geometry;

/**
 * MultiCurve: A collection of Curves
 *
 * @package GeoPHPGeometry
 */
abstract class MultiCurve extends MultiGeometry
{

    /**
     * @param Curve[] $components
     * @param bool    $allowEmptyComponents
     * @param string  $allowedComponentType
     */
    public function __construct(
        array $components = [],
        bool $allowEmptyComponents = true,
        string $allowedComponentType = Curve::class
    ) {
        parent::__construct($components, $allowEmptyComponents, $allowedComponentType);
    }

    /**
     * @return string returns "MultiCurve"
     */
    public function geometryType(): string
    {
        return Geometry::MULTI_CURVE;
    }

    /**
     * @return int 1
     */
    public function dimension(): int
    {
        return 1;
    }

    /**
     * MultiCurve is closed if all it's components are closed
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        foreach ($this->getComponents() as $line) {
            if (!$line->isClosed()) {
                return false;
            }
        }
        return true;
    }
}
