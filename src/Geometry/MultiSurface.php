<?php

namespace geoPHP\Geometry;

/**
 * @package GeoPHPGeometry
 */
abstract class MultiSurface extends MultiGeometry
{

    public function __construct(
        array $components = [],
        bool $allowEmptyComponents = true,
        string $allowedComponentType = Surface::class
    ) {
        parent::__construct($components, $allowEmptyComponents, $allowedComponentType);
    }

    /**
     * @return string "MultiSurface"
     */
    public function geometryType(): string
    {
        return Geometry::MULTI_SURFACE;
    }

    /**
     * @return int 2
     */
    public function dimension(): int
    {
        return 2;
    }
}
