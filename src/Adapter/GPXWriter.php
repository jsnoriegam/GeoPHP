<?php
namespace geoPHP\Adapter;

use geoPHP\Geometry\Collection;
use geoPHP\Geometry\Geometry;
use geoPHP\Geometry\GeometryCollection;
use geoPHP\Geometry\Point;
use geoPHP\Geometry\LineString;
use geoPHP\Geometry\MultiLineString;

/*
 * Copyright (c) Patrick Hayes
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Geometry/GPX encoder/decoder
 */
trait GPXWriter
{

    /**
     * @var string Name-space string. eg 'georss:'
     */
    private $nss = '';

    /**
     * @var GpxTypes
     */
    protected $gpxTypes;

    /**
     * Serialize geometries into a GPX string.
     *
     * @param Geometry|GeometryCollection $geometry
     * @param string $namespace
     * @param array<array> $allowedElements Which elements can be added to each GPX type
     *              If not specified, every element defined in the GPX specification can be added
     *              Can be overwritten with an associative array, with type name in keys.
     *              eg.: ['wptType' => ['ele', 'name'], 'trkptType' => ['ele'], 'metadataType' => null]
     * @return string The GPX string representation of the input geometries
     */
    public function write(Geometry $geometry, string $namespace = '', array $allowedElements = []): string
    {
        $namespace = trim($namespace);
        if (!empty($namespace)) {
            $this->nss = $namespace . ':';
        }
        $this->gpxTypes = new GpxTypes($allowedElements);

        return
            '<?xml version="1.0" encoding="UTF-8"?>
<' . $this->nss . 'gpx creator="geoPHP" version="1.1"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xmlns="http://www.topografix.com/GPX/1/1"
  xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd" >

' . $this->geometryToGPX($geometry) .
            '</' . $this->nss . 'gpx>
';
    }

    /**
     * @param Geometry|Collection $geometry
     * @return string
     */
    protected function geometryToGPX($geometry): string
    {
        switch ($geometry->geometryType()) {
            case Geometry::POINT:
                /** @var Point $geometry */
                return $this->pointToGPX($geometry);
            case Geometry::LINESTRING:
            case Geometry::MULTI_LINESTRING:
                /** @var LineString $geometry */
                return $this->linestringToGPX($geometry);
            case Geometry::POLYGON:
            case Geometry::MULTI_POINT:
            case Geometry::MULTI_POLYGON:
            case Geometry::GEOMETRY_COLLECTION:
                /** @var GeometryCollection $geometry */
                return $this->collectionToGPX($geometry);
        }
        return '';
    }

    /**
     * @param Point $geom
     * @param string $tag Can be "wpt", "trkpt" or "rtept"
     * @return string
     */
    private function pointToGPX($geom, $tag = 'wpt'): string
    {
        if ($geom->isEmpty() || ($tag === 'wpt' && !in_array($tag, $this->gpxTypes->get('gpxType')))) {
            return '';
        }
        $indent = $tag === 'trkpt' ? "\t\t" : ($tag === 'rtept' ? "\t" : '');

        if ($geom->hasZ() || $geom->getData() !== null) {
            $node = $indent . "<" . $this->nss . $tag . " lat=\"" . $geom->getY() . "\" lon=\"" . $geom->getX() . "\">\n";
            if ($geom->hasZ()) {
                $geom->setData('ele', $geom->getZ());
            }
            $node .= self::processGeometryData($geom, $this->gpxTypes->get($tag . 'Type'), $indent . "\t") .
                $indent . "</" . $this->nss . $tag . ">\n";
            if ($geom->hasZ()) {
                $geom->setData('ele', null);
            }
            return $node;
        }
        return $indent . "<" . $this->nss . $tag . " lat=\"" . $geom->getY() . "\" lon=\"" . $geom->getX() . "\" />\n";
    }

    /**
     * Writes a LineString or MultiLineString to the GPX
     *
     * The (Multi)LineString will be included in a <trk></trk> block
     * The LineString or each LineString of the MultiLineString will be in <trkseg> </trkseg> inside the <trk>
     *
     * @param LineString|MultiLineString $geom
     * @return string
     */
    private function linestringToGPX($geom): string
    {
        $isTrack = $geom->getData('gpxType') === 'route' ? false : true;
        if ($geom->isEmpty() || !in_array($isTrack ? 'trk' : 'rte', $this->gpxTypes->get('gpxType'))) {
            return '';
        }

        if ($isTrack) { // write as <trk>
            /** @noinspection SpellCheckingInspection */
            $gpx = "<" . $this->nss . "trk>\n" . self::processGeometryData($geom, $this->gpxTypes->get('trkType'));
            $components = $geom->geometryType() === 'LineString' ? [$geom] : $geom->getComponents();
            foreach ($components as $lineString) {
                $gpx .= "\t<" . $this->nss . "trkseg>\n";
                foreach ($lineString->getPoints() as $point) {
                    $gpx .= $this->pointToGPX($point, 'trkpt');
                }
                $gpx .= "\t</" . $this->nss . "trkseg>\n";
            }
            /** @noinspection SpellCheckingInspection */
            $gpx .= "</" . $this->nss . "trk>\n";
        } else { // write as <rte>
            /** @noinspection SpellCheckingInspection */
            $gpx = "<" . $this->nss . "rte>\n" . self::processGeometryData($geom, $this->gpxTypes->get('rteType'));
            foreach ($geom->getPoints() as $point) {
                $gpx .= $this->pointToGPX($point, 'rtept');
            }
            /** @noinspection SpellCheckingInspection */
            $gpx .= "</" . $this->nss . "rte>\n";
        }

        return $gpx;
    }

    /**
     * @param Collection $geometry
     * @return string
     */
    public function collectionToGPX($geometry): string
    {
        $metadata = self::processGeometryData($geometry, $this->gpxTypes->get('metadataType'));
        $metadata = empty($metadata) || !in_array('metadataType', $this->gpxTypes->get('gpxType')) ?
            '' : "<metadata>\n" . $metadata . "</metadata>\n\n";
        $wayPoints = $routes = $tracks = "";

        foreach ($geometry->getComponents() as $component) {
            $geometryType = $component->geometryType();
            
            if (strpos($geometryType, 'Point') !== false) {
                $wayPoints .= $this->geometryToGPX($component);
            } elseif (strpos($geometryType, 'Linestring') !== false) {
                if ($component->getData('gpxType') === 'route') {
                    $routes .= $this->geometryToGPX($component);
                } else {
                    $tracks .= $this->geometryToGPX($component);
                }
            } else {
                return $this->geometryToGPX($component);
            }
        }

        return $metadata . $wayPoints . $routes . $tracks;
    }

    /**
     * @param Geometry $geometry
     * @param string[] $tagList Allowed tags
     * @param string $indent
     * @return string
     */
    protected static function processGeometryData($geometry, $tagList, $indent = "\t"): string
    {
        $tags = '';
        if ($geometry->getData() !== null) {
            foreach ($tagList as $tagName) {
                if ($geometry->hasDataProperty($tagName)) {
                    $tags .= self::createNodes($tagName, $geometry->getData($tagName), $indent) . "\n";
                }
            }
        }
        return $tags;
    }

    /**
     * @param string $tagName
     * @param string|array<array> $value
     * @param string $indent
     * @return string
     */
    protected static function createNodes($tagName, $value, $indent): string
    {
        $attributes = '';
        if (!is_array($value)) {
            $returnValue = $value;
        } else {
            $returnValue = '';
            if (array_key_exists('@attributes', $value)) {
                $attributes = '';
                foreach ($value['@attributes'] as $attributeName => $attributeValue) {
                    $attributes .= ' ' . $attributeName . '="' . $attributeValue . '"';
                }
                unset($value['@attributes']);
            }
            foreach ($value as $subKey => $subValue) {
                $returnValue .= "\n" . self::createNodes($subKey, $subValue, $indent . "\t") . "\n" . $indent;
            }
        }
        return $indent . "<{$tagName}{$attributes}>{$returnValue}</{$tagName}>";
    }
}
