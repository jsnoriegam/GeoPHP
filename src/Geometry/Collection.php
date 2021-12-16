<?php

namespace geoPHP\Geometry;

use geoPHP\Exception\InvalidGeometryException;
use geoPHP\geoPHP;

/**
 * Collection: Abstract class for compound geometries
 *
 * A geometry is a collection if it is made up of other component geometries. Therefore everything except a Point
 * is a Collection. For example a LingString is a collection of Points. A Polygon is a collection of LineStrings etc.
 *
 * @package GeoPHPGeometry
 */
abstract class Collection extends Geometry
{

    /**
     * @var Geometry[]|Collection[]
     */
    protected $components = [];

    /**
     * @var bool True if Geometry has Z (altitude) value
     */
    protected $hasZ = false;

    /**
     * @var bool True if Geometry has M (measure) value
     */
    protected $isMeasured = false;
    
    /**
     * Constructor: Checks and sets component geometries
     *
     * @param  Geometry[] $components           array of geometries
     * @param  bool       $allowEmptyComponents Allow creating geometries with empty components. Default false.
     * @param  string     $allowedComponentType A class-type the components have to be instance of.
     * @throws InvalidGeometryException
     */
    public function __construct(
        array $components = [],
        bool $allowEmptyComponents = false,
        string $allowedComponentType = Geometry::class
    ) {
        $componentCount = count($components);
        for ($i = 0; $i < $componentCount; ++$i) {
            if ($components[$i] instanceof $allowedComponentType) {
                if (!$allowEmptyComponents && $components[$i]->isEmpty()) {
                    throw new InvalidGeometryException(
                        'Cannot create a collection of empty ' .
                            $components[$i]->geometryType() . 's (' . ($i + 1) . '. component)'
                    );
                }
                if ($components[$i]->hasZ() && !$this->hasZ) {
                    $this->hasZ = true;
                }
                if ($components[$i]->isMeasured() && !$this->isMeasured) {
                    $this->isMeasured = true;
                }
            } else {
                $componentType = gettype($components[$i]) !== 'object' ?
                    gettype($components[$i]) :
                    get_class($components[$i]);
                
                throw new InvalidGeometryException(
                    'Cannot create a collection of ' . $componentType . ' components, ' .
                        'expected type is ' . $allowedComponentType
                );
            }
        }
        
        $this->components = $components;
    }

    /**
     * Returns Collection component geometries
     *
     * @return Geometry[]
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Inverts x and y coordinates
     * Useful for old data still using lng lat
     *
     * @return self
     * */
    public function invertXY(): self
    {
        foreach ($this->components as $component) {
            $component->invertXY();
        }
        $this->setGeos(null);
        return $this;
    }

    /**
     * @return array{'minx'?:float|null, 'miny'?:float|null, 'maxx'?:float|null, 'maxy'?:float|null}
     */
    public function getBBox(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        // use GEOS library
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            return $this->getBBoxWithGeos($geosObj);
        }

        // Go through each component and get the max and min x and y
        $maxX = $maxY = $minX = $minY = 0;
        foreach ($this->components as $i => $component) {
            $componentBoundingBox = $component->getBBox();

            if (empty($componentBoundingBox)) {
                continue;
            }
            
            // On the first run through, set the bounding box to the component's bounding box
            if ($i === 0) {
                $maxX = $componentBoundingBox['maxx'];
                $maxY = $componentBoundingBox['maxy'];
                $minX = $componentBoundingBox['minx'];
                $minY = $componentBoundingBox['miny'];
            }

            // Do a check and replace on each boundary, slowly growing the bounding box
            $maxX = $componentBoundingBox['maxx'] > $maxX ? $componentBoundingBox['maxx'] : $maxX;
            $maxY = $componentBoundingBox['maxy'] > $maxY ? $componentBoundingBox['maxy'] : $maxY;
            $minX = $componentBoundingBox['minx'] < $minX ? $componentBoundingBox['minx'] : $minX;
            $minY = $componentBoundingBox['miny'] < $minY ? $componentBoundingBox['miny'] : $minY;
        }

        return [
            'maxy' => $maxY,
            'miny' => $minY,
            'maxx' => $maxX,
            'minx' => $minX,
        ];
    }
    
    /**
     * @param \GEOSGeometry $geosObj
     * @return array{'minx'?:float|null, 'miny'?:float|null, 'maxx'?:float|null, 'maxy'?:float|null}
     */
    private function getBBoxWithGeos($geosObj): array
    {
        // @codeCoverageIgnoreStart
        /** @noinspection PhpUndefinedMethodInspection */
        $envelope = $geosObj->envelope();
        
        /** @noinspection PhpUndefinedMethodInspection */
        if ($envelope->typeName() === 'Point') {
            return geoPHP::geosToGeometry($envelope)->getBBox();
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $geosRing = $envelope->exteriorRing();
        
        /** @noinspection PhpUndefinedMethodInspection */
        return [
            'maxy' => $geosRing->pointN(3)->getY(),
            'miny' => $geosRing->pointN(1)->getY(),
            'maxx' => $geosRing->pointN(1)->getX(),
            'minx' => $geosRing->pointN(3)->getX(),
        ];
        // @codeCoverageIgnoreEnd
    }
    
    /**
     * check if Geometry has a measure value
     *
     * @return bool true if collection has measure values
     */
    public function isMeasured(): bool
    {
        return $this->isMeasured;
    }

    /**
     * check if Geometry has Z (altitude) coordinate
     *
     * @return bool true if geometry has a Z-value
     */
    public function hasZ(): bool
    {
        return $this->hasZ;
    }
    
    /**
     * Returns every sub-geometry as a multidimensional array
     *
     * @return array<int, array>
     */
    public function asArray(): array
    {
        $array = [];
        foreach ($this->components as $component) {
            $array[] = $component->asArray();
        }
        return $array;
    }

    /**
     * @return int
     */
    public function numGeometries(): int
    {
        return count($this->components);
    }

    /**
     * Returns the 1-based Nth geometry.
     *
     * @param  int $n 1-based geometry number
     * @return Geometry|null
     */
    public function geometryN(int $n)
    {
        return isset($this->components[$n - 1]) ? $this->components[$n - 1] : null;
    }

    /**
     * A collection is not empty if it has at least one non empty component.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        foreach ($this->components as $component) {
            if (!$component->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return int
     */
    public function numPoints(): int
    {
        $num = 0;
        foreach ($this->components as $component) {
            $num += $component->numPoints();
        }
        return $num;
    }

    /**
     * @return Point[]
     */
    public function getPoints(): array
    {
        $points = [];

        // Same as array_merge($points, $component->getPoints()), but 500× faster
        static::getPointsRecursive($this, $points);
        return $points;
    }

    /**
     * @param  Collection $geometry The geometry from which points will be extracted
     * @param  Point[]    $points   Result array as reference
     * @return void
     */
    private static function getPointsRecursive(Geometry $geometry, array &$points)
    {
        foreach ($geometry->components as $component) {
            if ($component instanceof Point) {
                $points[] = $component;
            } else {
                /** @var Collection $component */
                static::getPointsRecursive($component, $points);
            }
        }
    }

    /**
     * Returns TRUE if the given Geometries are "spatially equal".
     * Ordering of points can be different but represent the same geometry structure
     * 
     * @param  Geometry $geometry
     * @return bool
     */
    public function equals(Geometry $geometry): bool
    {
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            $geosObj2 = $geometry->getGeos();
            return is_object($geosObj2) ? $geosObj->equals($geosObj2) : false;
            // @codeCoverageIgnoreEnd
        }

        $thisPoints = $this->getPoints();
        $otherPoints = $geometry->getPoints();

        /*
        // using some sort of simplification method that strips redundant vertices (that are all in a row)
        // Hint: this mehtod is mostly slower as long as number of points is less than 1000
        $asWkt = function(Point $pt){
            return implode(' ', $pt->asArray());
        };
        $ptsA = array_unique(array_map($asWkt, $thisPoints));
        $ptsB = array_unique(array_map($asWkt, $otherPoints));

        return count(array_diff($ptsA, $ptsB)) === 0;
        */
        
        // To test for equality we check to make sure that there is a matching point
        // in the other geometry for every point in this geometry.
        // This is slightly more strict than the standard, which
        // uses Within(A,B) = true and Within(B,A) = true
        
        // First do a check to make sure they have the same number of vertices
        if (count($thisPoints) !== count($otherPoints)) {
            return false;
        }

        foreach ($thisPoints as $point) {
            $foundMatch = false;
            foreach ($otherPoints as $key => $testPoint) {
                if ($point->equals($testPoint)) {
                    $foundMatch = true;
                    unset($otherPoints[$key]);
                    break;
                }
            }
            if (!$foundMatch) {
                return false;
            }
        }

        // All points match, return TRUE
        return true;
    }

    /**
     * Get all underlying components separated
     *
     * @param  bool $toArray return underlying components as LineStrings/Points or as array of coordinate values.
     * @return LineString[]|Point[]|array{}|array<array>
     */
    public function explode(bool $toArray = false): array
    {
        $parts = [];
        foreach ($this->getComponents() as $component) {
            foreach ($component->explode($toArray) as $part) {
                $parts[] = $part;
            }
        }
        return $parts;
    }

    /**
     * @return void
     */
    public function flatten()
    {
        if ($this->hasZ() || $this->isMeasured()) {
            foreach ($this->getComponents() as $component) {
                $component->flatten();
            }
            $this->hasZ = false;
            $this->isMeasured = false;
            $this->setGeos(null);
        }
    }

    /**
     * @return float|null
     */
    public function distance(Geometry $geometry)
    {
        $geosObj = $this->getGeos();
        if (is_object($geosObj)) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            $geosObj2 = $geometry->getGeos();
            return is_object($geosObj2) ? $geosObj->distance($geosObj2) : null;
            // @codeCoverageIgnoreEnd
        }
        
        $distance = null;
        foreach ($this->getComponents() as $component) {
            $checkDistance = $component->distance($geometry);
            if ($checkDistance === 0.0) {
                return 0.0;
            }
            if ($checkDistance === null) {
                continue;
            }
            $distance = ($distance ?? $checkDistance);

            if ($checkDistance < $distance) {
                $distance = $checkDistance;
            }
        }
        
        return $distance;
    }
    
    public function translate($dx = 0, $dy = 0, $dz = 0)
    {
        foreach ($this->getComponents() as $component) {
            $component->translate($dx, $dy, $dz);
        }
    }
}
