<?php
namespace GeoPHP\Adapter;

use GeoPHP\Geometry\Collection;
use GeoPHP\GeoPHP;
use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\GeometryCollection;
use GeoPHP\Geometry\Point;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\Polygon;

/*
 * Copyright (c) Patrick Hayes
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Geometry/GeoRSS encoder/decoder
 */
class GeoRSS implements GeoAdapter
{

    /**
     * @var \DOMDocument $xmlObject
     */
    protected $xmlObject;
    
    /**
     * @var string Name-space string. eg 'georss:'
     */
    private $nss = '';

    /**
     * Read GeoRSS string into geometry objects
     *
     * @param string $georss - an XML feed containing geoRSS
     * @return Geometry|GeometryCollection
     * @throws \Exception
     */
    public function read(string $georss): Geometry
    {
        return $this->geomFromText($georss);
    }

    /**
     * Serialize geometries into a GeoRSS string.
     *
     * @param Geometry $geometry
     * @param string $namespace
     * @return string The georss string representation of the input geometries
     */
    public function write(Geometry $geometry, $namespace = ''): string
    {
        $namespace = trim($namespace);
        if (!empty($namespace)) {
            $this->nss = $namespace . ':';
        }
        return $this->geometryToGeoRSS($geometry);
    }

    /**
     * Creates a new geometry-object from input-string.
     *
     * @param string $text
     * @return Geometry|GeometryCollection
     * @throws \Exception
     */
    public function geomFromText(string $text): Geometry
    {
        // Change to lower-case, strip all CDATA, and de-namespace
        $text = strtolower($text);
        $text = preg_replace('/<!\[cdata\[(.*?)\]\]>/s', '', $text);

        // Load into DOMDocument
        $xmlObject = new \DOMDocument();
        
        if ($xmlObject->loadXML($text) === false) {
            throw new \Exception("Invalid GeoRSS: " . $text);
        }

        $this->xmlObject = $xmlObject;
        try {
            $geom = $this->geomFromXML();
        } catch (\Exception $e) {
            throw new \Exception("Cannot read geometry from GeoRSS: " . $e->getMessage());
        }

        return $geom;
    }

    /**
     * @return Geometry|GeometryCollection
     * @throws \Exception
     */
    protected function geomFromXML()
    {
        $geometries = array_merge(
            $this->parsePoints(),
            $this->parseLines(),
            $this->parsePolygons(),
            $this->parseBoxes(),
            $this->parseCircles()
        );

        if (empty($geometries)) {
            throw new \Exception("Invalid / empty GeoRSS.");
        }

        return GeoPHP::geometryReduce($geometries);
    }

    /**
     * @param string $string
     * @return Point[]
     */
    protected function getPointsFromCoordinates(string $string): array
    {
        $coordinates = [];
        $latitudeAndLongitude = explode(' ', $string);
        $lat = 0;
        foreach ($latitudeAndLongitude as $key => $item) {
            if (!($key % 2)) {
                // It's a latitude
                $lat = is_numeric($item) ? $item : null;
            } else {
                // It's a longitude
                $lon = is_numeric($item) ? $item : null;
                $coordinates[] = new Point($lon, $lat);
            }
        }
        return $coordinates;
    }

    /**
     * @return Point[]
     */
    protected function parsePoints(): array
    {
        $points = [];
        $pointElements = $this->xmlObject->getElementsByTagName('point');
        foreach ($pointElements as $pt) {
            $pointArray = $this->getPointsFromCoordinates(trim($pt->firstChild->nodeValue));
            $points[] = !empty($pointArray) ? $pointArray[0] : new Point();
        }
        return $points;
    }

    /**
     * @return LineString[]
     */
    protected function parseLines(): array
    {
        $lines = [];
        $lineElements = $this->xmlObject->getElementsByTagName('line');
        foreach ($lineElements as $line) {
            $components = $this->getPointsFromCoordinates(trim($line->firstChild->nodeValue));
            $lines[] = new LineString($components);
        }
        return $lines;
    }

    /**
     * @return Polygon[]
     */
    protected function parsePolygons(): array
    {
        $polygons = [];
        $polygonElements = $this->xmlObject->getElementsByTagName('polygon');
        foreach ($polygonElements as $polygon) {
            /** @noinspection PhpUndefinedMethodInspection */
            if ($polygon->hasChildNodes()) {
                $points = $this->getPointsFromCoordinates(trim($polygon->firstChild->nodeValue));
                $exteriorRing = new LineString($points);
                $polygons[] = new Polygon([$exteriorRing]);
            } else {
                // It's an EMPTY polygon
                $polygons[] = new Polygon();
            }
        }
        return $polygons;
    }

    /**
     * Boxes are rendered into polygons.
     *
     * @return Polygon[]
     */
    protected function parseBoxes(): array
    {
        $polygons = [];
        $boxElements = $this->xmlObject->getElementsByTagName('box');
        foreach ($boxElements as $box) {
            $parts = explode(' ', trim($box->firstChild->nodeValue));
            $components = [
                new Point($parts[3], $parts[2]),
                new Point($parts[3], $parts[0]),
                new Point($parts[1], $parts[0]),
                new Point($parts[1], $parts[2]),
                new Point($parts[3], $parts[2]),
            ];
            $exteriorRing = new LineString($components);
            $polygons[] = new Polygon([$exteriorRing]);
        }
        return $polygons;
    }

    /**
     * Circles are rendered into points
     * @todo Add good support once we have circular-string geometry support
     * @return Point[]
     */
    protected function parseCircles(): array
    {
        $points = [];
        $circleElements = $this->xmlObject->getElementsByTagName('circle');
        foreach ($circleElements as $circle) {
            $parts = explode(' ', trim($circle->firstChild->nodeValue));
            $points[] = new Point($parts[1], $parts[0]);
        }
        return $points;
    }

    /**
     * @param Geometry $geometry
     * @return string
     */
    protected function geometryToGeoRSS(Geometry $geometry): string
    {
        $type = $geometry->geometryType();
        switch ($type) {
            case Geometry::POINT:
                /** @var Point $geometry */
                return $this->pointToGeoRSS($geometry);
            case Geometry::LINESTRING:
                /** @noinspection PhpParamsInspection */
                /** @var LineString $geometry */
                return $this->linestringToGeoRSS($geometry);
            case Geometry::POLYGON:
                /** @noinspection PhpParamsInspection */
                /** @var Polygon $geometry */
                return $this->PolygonToGeoRSS($geometry);
            case Geometry::MULTI_POINT:
            case Geometry::MULTI_LINESTRING:
            case Geometry::MULTI_POLYGON:
            case Geometry::GEOMETRY_COLLECTION:
                /** @noinspection PhpParamsInspection */
                /** @var GeometryCollection $geometry */
                return $this->collectionToGeoRSS($geometry);
        }
        return '';
    }

    /**
     * @param Point $geometry
     * @return string
     */
    private function pointToGeoRSS(Point $geometry): string
    {
        return '<' . $this->nss . 'point>' . $geometry->getY() . ' ' . $geometry->getX() . '</' . $this->nss . 'point>';
    }

    /**
     * @param LineString $geometry
     * @return string
     */
    private function linestringToGeoRSS(LineString $geometry): string
    {
        $output = '<' . $this->nss . 'line>';
        foreach ($geometry->getComponents() as $k => $point) {
            $output .= $point->getY() . ' ' . $point->getX();
            if ($k < ($geometry->numGeometries() - 1)) {
                $output .= ' ';
            }
        }
        $output .= '</' . $this->nss . 'line>';
        return $output;
    }

    /**
     * @param Polygon $geometry
     * @return string
     */
    private function polygonToGeoRSS(Polygon $geometry): string
    {
        $output = '<' . $this->nss . 'polygon>';
        $exteriorRing = $geometry->exteriorRing();
        foreach ($exteriorRing->getComponents() as $k => $point) {
            $output .= $point->getY() . ' ' . $point->getX();
            if ($k < ($exteriorRing->numGeometries() - 1)) {
                $output .= ' ';
            }
        }
        $output .= '</' . $this->nss . 'polygon>';
        return $output;
    }

    /**
     * @param Collection $geometry
     * @return string
     */
    public function collectionToGeoRSS(Collection $geometry): string
    {
        $georss = '<' . $this->nss . 'where>';
        $components = $geometry->getComponents();
        foreach ($components as $component) {
            $georss .= $this->geometryToGeoRSS($component);
        }

        $georss .= '</' . $this->nss . 'where>';

        return $georss;
    }
}
