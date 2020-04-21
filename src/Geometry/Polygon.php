<?php

namespace geoPHP\Geometry;

use geoPHP\Exception\InvalidGeometryException;
use geoPHP\geoPHP;

/**
 * Polygon: A polygon is a plane figure that is bounded by a closed path,
 * composed of a finite sequence of straight line segments.
 *
 * @package GeoPHPGeometry
 */
class Polygon extends Surface
{

    /**
     * @param  LineString[] $components
     * @param  bool|false   $forceCreate force creation even if polygon is invalid because it is not closed
     * @throws \InvalidGeometryException
     */
    public function __construct(array $components = [], bool $forceCreate = false)
    {
        parent::__construct($components, true, LineString::class);

        foreach ($this->getComponents() as $i => $component) {
            if ($component->numPoints() < 4) {
                throw new InvalidGeometryException(
                    'Cannot create Polygon: Invalid number of points in LinearRing. Found ' .
                    $component->numPoints() . ', but expected more than 3.'
                );
            }
            if (!$component->isClosed()) {
                if ($forceCreate) {
                    $this->components[$i] = new LineString(
                        array_merge($component->getComponents(), [$component->startPoint()])
                    );
                } else {
                    throw new InvalidGeometryException(
                        'Cannot create Polygon: contains a non-closed ring (first point: '
                            . implode(' ', $component->startPoint()->asArray()) . ', last point: '
                            . implode(' ', $component->endPoint()->asArray()) . ')'
                    );
                }
            }
            // This check is tooo expensive
            //if (!$component->isSimple() && !$forceCreate) {
            //    throw new \Exception('Cannot create Polygon: geometry should be simple');
            //}
        }
    }

    /**
     * @return string "Polygon"
     */
    public function geometryType(): string
    {
        return Geometry::POLYGON;
    }

    /**
     * @return int 2
     */
    public function dimension(): int
    {
        return 2;
    }

    /**
     * @param bool|false $exteriorOnly Calculate the area of exterior ring only, or the polygon with holes
     * @param bool|false $signed       Usually we want to get positive area, but vertices order (CW or CCW) can be
     *                                 determined from signed area.
     *
     * @return float
     */
    public function getArea(bool $exteriorOnly = false, bool $signed = false): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        if ($this->getGeos() && $exteriorOnly === false) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return (float) $this->getGeos()->area();
            // @codeCoverageIgnoreEnd
        }

        $exteriorRing = $this->components[0];
        $points = $exteriorRing->getComponents();

        $numPoints = count($points);
        if ($numPoints === 0) {
            return 0.0;
        }
        $a = 0;
        foreach ($points as $k => $p) {
            $j = ($k + 1) % $numPoints;
            $a = $a + ($p->getX() * $points[$j]->getY()) - ($p->getY() * $points[$j]->getX());
        }

        $area = $signed ? ($a / 2) : abs(($a / 2));

        if ($exteriorOnly === true) {
            return (float) $area;
        }
        foreach ($this->components as $delta => $component) {
            if ($delta != 0) {
                $innerPoly = new Polygon([$component]);
                $area -= $innerPoly->getArea();
            }
        }
        return (float) $area;
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
        $totalArea = 0.0;
        foreach ($this->getComponents() as $i => $component) {
            $ca = $this->getRingCentroidAndArea($component);
            if ($i === 0) {
                $totalArea += $ca['area'];
                $x += $ca['x'] * $ca['area'];
                $y += $ca['y'] * $ca['area'];
            } else {
                $totalArea -= $ca['area'];
                $x += $ca['x'] * $ca['area'] * -1;
                $y += $ca['y'] * $ca['area'] * -1;
            }
        }
        if ($totalArea == 0.0) {
            return new Point();
        }
        return new Point($x / $totalArea, $y / $totalArea);
    }

    /**
     * @param  LineString $ring
     * @return array
     */
    protected function getRingCentroidAndArea(LineString $ring): array
    {
        $area = (new Polygon([$ring]))->area(true, true);

        $points = $ring->getPoints();
        $numPoints = count($points);
        if ($numPoints === 0 || $area == 0.0) {
            return ['area' => 0.0, 'x' => null, 'y' => null];
        }
        $x = 0;
        $y = 0;
        foreach ($points as $k => $point) {
            $j = ($k + 1) % $numPoints;
            $P = ($point->getX() * $points[$j]->getY()) - ($point->getY() * $points[$j]->getX());
            $x += ($point->getX() + $points[$j]->getX()) * $P;
            $y += ($point->getY() + $points[$j]->getY()) * $P;
        }
        
        return [
            'area' => abs($area),
            'x' => $x / (6 * $area),
            'y' => $y / (6 * $area)
        ];
    }

    /**
     * Find the outermost point from the centroid
     *
     * @return Point the outermost point
     */
    public function outermostPoint(): Point
    {
        $centroid = $this->getCentroid();
        if ($centroid->isEmpty()) {
            return $centroid;
        }

        $maxDistance = 0;
        $maxPoint = new Point;

        foreach ($this->exteriorRing()->getPoints() as $point) {
            $distance = $centroid->distance($point);

            if ($distance > $maxDistance) {
                $maxDistance = $distance;
                $maxPoint = $point;
            }
        }

        return $maxPoint;
    }

    /**
     * @return LineString
     */
    public function exteriorRing()
    {
        if ($this->isEmpty()) {
            return new LineString();
        }
        return $this->components[0];
    }

    /**
     * @return int
     */
    public function numInteriorRings(): int
    {
        return $this->isEmpty() ? 0 : $this->numGeometries() - 1;
    }

    /**
     * Returns the linestring for the nth interior ring of the polygon. Interior rings are holes in the polygon.
     *
     * @param  int $n 1-based geometry number
     * @return LineString
     */
    public function interiorRingN(int $n)
    {
        return $this->numInteriorRings() < $n ? new LineString : $this->geometryN($n + 1);
    }

    /**
     * @return bool
     */
    public function isSimple(): bool
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->isSimple();
            // @codeCoverageIgnoreEnd
        }

        $segments = $this->explode(true);
        
        //TODO: instead of this O(n^2) algorithm implement Shamos-Hoey Algorithm which is only O(n*log(n))
        foreach ($segments as $i => $segment) {
            foreach ($segments as $j => $checkSegment) {
                if ($i != $j) {
                    if (Geometry::segmentIntersects($segment[0], $segment[1], $checkSegment[0], $checkSegment[1])) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * If GEOS is not available, it is still a quite simple test of validity for polygons.
     * E.g. a test for self-intersections is missing.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->checkValidity()['valid'];
            // @codeCoverageIgnoreEnd
        }
        
        // all rings (LineStrings) have to be valid itself
        /**
 * @var \geoPHP\Geometry\LineString $ring
*/
        foreach ($this->components as $ring) {
            if ($ring->isEmpty()) {
                continue;
            }
            if (!$ring->isValid()) {
                return false;
            }
            $wkt = str_ireplace(['LINESTRING(',')'], '', $ring->asText());
            $pts = array_unique(array_map('trim', explode(',', $wkt)));
            if (count($pts) < 3) {
                return false;
            }
        }
        
        return $this->isSimple();
    }

    /**
     * For a given point, determine whether it's bounded by the given polygon.
     * Adapted from @source http://www.assemblysys.com/dataServices/php_pointinpolygon.php
     *
     * @see http://en.wikipedia.org/wiki/Point%5Fin%5Fpolygon
     *
     * @param  Point   $point
     * @param  boolean $pointOnBoundary - whether a boundary should be considered "in" or not
     * @param  boolean $pointOnVertex   - whether a vertex should be considered "in" or not
     * @return bool
     */
    public function pointInPolygon(Point $point, bool $pointOnBoundary = true, bool $pointOnVertex = true): bool
    {
        $vertices = $this->getPoints();

        // Check if the point sits exactly on a vertex
        if ($this->pointOnVertex($point, $vertices)) {
            return $pointOnVertex;
        }

        // Check if the point is inside the polygon or on the boundary
        $intersections = 0;
        $verticesCount = count($vertices);
        for ($i = 1; $i < $verticesCount; $i++) {
            $vertex1 = $vertices[$i - 1];
            $vertex2 = $vertices[$i];
            if ($vertex1->getY() == $vertex2->getY()
                && $vertex1->getY() == $point->getY()
                && $point->getX() > min($vertex1->getX(), $vertex2->getX())
                && $point->getX() < max($vertex1->getX(), $vertex2->getX())
            ) {
                // Check if point is on an horizontal polygon boundary
                return $pointOnBoundary;
            }
            if ($point->getY() > min($vertex1->getY(), $vertex2->getY())
                && $point->getY() <= max($vertex1->getY(), $vertex2->getY())
                && $point->getX() <= max($vertex1->getX(), $vertex2->getX())
                && $vertex1->getY() != $vertex2->getY()
            ) {
                $xinters =
                        ($point->getY() - $vertex1->getY()) * ($vertex2->getX() - $vertex1->getX())
                        / ($vertex2->getY() - $vertex1->getY())
                        + $vertex1->getX();
                if ($xinters == $point->getX()) {
                    // Check if point is on the polygon boundary (other than horizontal)
                    return $pointOnBoundary;
                }
                if ($vertex1->getX() == $vertex2->getX() || $point->getX() <= $xinters) {
                    $intersections++;
                }
            }
        }
        
        // If the number of edges we passed through is even, then it's in the polygon.
        return ($intersections % 2 != 0);
    }

    /**
     * @param  Point $point
     * @return bool
     */
    public function pointOnVertex(Point $point): bool
    {
        foreach ($this->getPoints() as $vertex) {
            if ($point->equals($vertex)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether the given geometry is spatially inside the Polygon
     * TODO: rewrite this. Currently supports point, linestring and polygon with only outer ring
     *
     * @param  Geometry $geometry
     * @return bool
     */
    public function contains(Geometry $geometry): bool
    {
        if ($this->getGeos()) {
            // @codeCoverageIgnoreStart
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getGeos()->contains($geometry->getGeos());
            // @codeCoverageIgnoreEnd
        }

        $isInside = false;
        foreach ($geometry->getPoints() as $p) {
            if ($this->pointInPolygon($p)) {
                $isInside = true; // at least one point of the innerPoly is inside the outerPoly
                break;
            }
        }
        if (!$isInside) {
            return false;
        }

        if ($geometry->geometryType() === Geometry::LINESTRING) {
            // do nothing
        } elseif ($geometry->geometryType() === Geometry::POLYGON) {
            $geometry = $geometry->exteriorRing();
        } else {
            return false;
        }

        foreach ($geometry->explode(true) as $innerEdge) {
            foreach ($this->exteriorRing()->explode(true) as $outerEdge) {
                if (Geometry::segmentIntersects($innerEdge[0], $innerEdge[1], $outerEdge[0], $outerEdge[1])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getBBox(): array
    {
        return $this->exteriorRing()->getBBox();
    }

    /**
     * @return LineString|MultiLineString
     */
    public function boundary(): Geometry
    {
        $rings = $this->getComponents();
        return count($rings) > 0 ? new MultiLineString($rings) : new LineString($rings);
    }
}
