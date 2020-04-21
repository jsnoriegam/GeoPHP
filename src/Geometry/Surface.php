<?php
namespace geoPHP\Geometry;

/**
 * A Surface is a 2-dimensional abstract geometric object.
 *
 * OGC 06-103r4 6.1.10 specification:
 * A simple Surface may consists of a single “patch” that is associated with one “exterior boundary” and 0 or more
 * “interior” boundaries. A single such Surface patch in 3-dimensional space is isometric to planar Surfaces, by a
 * simple affine rotation matrix that rotates the patch onto the plane z = 0. If the patch is not vertical, the
 * projection onto the same plane is an isomorphism, and can be represented as a linear transformation, i.e. an affine.
 *
 * @package GeoPHPGeometry
 */
abstract class Surface extends Collection
{

    /**
     * @param array  $components
     * @param bool   $allowEmptyComponents
     * @param string $allowedComponentType
     */
    public function __construct(
        array $components = [],
        bool $allowEmptyComponents = true,
        string $allowedComponentType = Curve::class
    ) {
        parent::__construct($components, $allowEmptyComponents, $allowedComponentType);
    }

    /**
     * @return string "Surface"
     */
    public function geometryType(): string
    {
        return Geometry::SURFACE;
    }

    /**
     * @return int 2
     */
    public function dimension(): int
    {
        return 2;
    }

    public function isClosed(): bool
    {
        return true;
    }

    public function getLength(): float
    {
        return 0.0;
    }

    public function length3D(): float
    {
        return 0.0;
    }

    public function haversineLength(): float
    {
        return 0.0;
    }

    public function greatCircleLength(float $radius = null): float
    {
        return 0.0;
    }
}
