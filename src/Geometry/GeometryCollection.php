<?php
namespace GeoPHP\Geometry;

use GeoPHP\GeoPHP;
use GeoPHP\Exception\InvalidGeometryException;

/**
 * GeometryCollection: A heterogeneous collection of geometries.
 *
 * @package GeoPHPGeometry
 * @author  Patrick Hayes, Péter Báthory, Swen Zanon
 */
class GeometryCollection extends MultiGeometry
{

    /**
     * @param Geometry[] $components Array of geometries. Components of GeometryCollection can be
     *                               any of valid Geometry types, including empty geometry
     *
     * @throws InvalidGeometryException
     */
    public function __construct(array $components = [])
    {
        parent::__construct($components, true);
    }

    /**
     * @return string "GeometryCollection"
     */
    public function geometryType(): string
    {
        return Geometry::GEOMETRY_COLLECTION;
    }

    /**
     * @return int Returns the highest spatial dimension of components
     */
    public function dimension(): int
    {
        $dimension = 0;
        foreach ($this->getComponents() as $component) {
            if ($component->dimension() > $dimension) {
                $dimension = $component->dimension();
            }
        }
        return $dimension;
    }

    /**
     * Prior version 2.0 PostGIS throws an exception if used with GEOMETRYCOLLECTION. From 2.0.0 up it returns NULL.
     * GEOS throws an IllegalArgumentException with "Operation not supported by GeometryCollection".
     *
     * @return Geometry
     */
    public function boundary(): Geometry
    {
        return new GeometryCollection();
    }

    /**
     * In a GeometryCollection, the centroid is equal to the centroid of
     * the set of component Geometries of highest dimension.
     * (since the lower-dimension geometries contribute zero "weight" to the centroid).
     *
     * @return Point
     * @throws \Exception
     */
    public function getCentroid(): Point
    {
        if ($this->isEmpty()) {
            return new Point();
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

        $geometries = $this->explodeGeometries();

        $highestDimension = 0;
        foreach ($geometries as $geometry) {
            if ($geometry->dimension() > $highestDimension) {
                $highestDimension = $geometry->dimension();
            }
            if ($highestDimension === 2) {
                break;
            }
        }

        $highestDimensionGeometries = [];
        foreach ($geometries as $geometry) {
            if ($geometry->dimension() === $highestDimension) {
                $highestDimensionGeometries[] = $geometry;
            }
        }

        $reducedGeometry = GeoPHP::geometryReduce($highestDimensionGeometries);
        if ($reducedGeometry->geometryType() === Geometry::GEOMETRY_COLLECTION) {
            throw new \Exception('Internal error: GeometryCollection->getCentroid() calculation failed.');
        }
        
        return $reducedGeometry->getCentroid();
    }

    /**
     * Returns every sub-geometry as a multidimensional array
     *
     * Because geometryCollections are heterogeneous we need to specify which type of geometries they contain.
     * We need to do this because, for example, there would be no way to tell the difference between a
     * MultiPoint or a LineString, since they share the same structure (collection
     * of points). So we need to call out the type explicitly.
     *
     * @return array<int, array<array|string>>
     */
    public function asArray(): array
    {
        $array = [];
        foreach ($this->getComponents() as $component) {
            $array[] = [
                'type' => $component->geometryType(),
                'components' => $component->asArray(),
            ];
        }
        return $array;
    }

    /**
     * @return Geometry[]|Collection[]
     */
    public function explodeGeometries(): array
    {
        $geometries = [];
        
        foreach ($this->components as $component) {
            if ($component->geometryType() === Geometry::GEOMETRY_COLLECTION) {
                /** @var GeometryCollection $component */
                $geometries = array_merge($geometries, $component->explodeGeometries());
            } else {
                $geometries[] = $component;
            }
        }
        
        return $geometries;
    }
}
