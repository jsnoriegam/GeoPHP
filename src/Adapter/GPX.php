<?php
namespace GeoPHP\Adapter;

use GeoPHP\Geometry\Collection;
use GeoPHP\GeoPHP;
use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\GeometryCollection;
use GeoPHP\Geometry\Point;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\MultiLineString;

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
class GPX implements GeoAdapter
{
    use GPXWriter;

    /**
     * @var GpxTypes
     */
    protected $gpxTypes;

    /**
     * @var \DOMXPath
     */
    protected $xpath;
    
    /**
     * @var bool
     */
    protected $parseGarminRpt = false;
    
    /**
     * @var Point[]
     */
    protected $trackFromRoute;
    
    /**
     * @var bool add elevation-data to every coordinate
     */
    public $withElevation = false;

    /**
     * Read GPX string into geometry object
     *
     * @param string $gpx A GPX string
     * @param array<array> $allowedElements Which elements can be read from each GPX type
     *              If not specified, every element defined in the GPX specification can be read
     *              Can be overwritten with an associative array, with type name in keys.
     *              eg.: ['wptType' => ['ele', 'name'], 'trkptType' => ['ele'], 'metadataType' => null]
     *
     * @return Geometry|GeometryCollection
     * @throws \Exception If GPX is not a valid XML
     */
    public function read(string $gpx, array $allowedElements = []): Geometry
    {
        // Converts XML tags to lower-case (DOMDocument functions are case sensitive)
        $gpx = preg_replace_callback("/(<\/?\w+)(.*?>)/", function ($m) {
            return strtolower($m[1]) . $m[2];
        }, $gpx);

        $this->gpxTypes = new GpxTypes($allowedElements);

        //libxml_use_internal_errors(true); // why?
        // Load into DOMDocument
        $xmlObject = new \DOMDocument('1.0', 'UTF-8');
        $xmlObject->preserveWhiteSpace = false;
        
        if ($xmlObject->loadXML($gpx) === false) {
            throw new \Exception("Invalid GPX: " . $gpx);
        }

        $this->parseGarminRpt = strpos($gpx, 'gpxx:rpt') > 0;

        // Initialize XPath parser if needed (currently only for Garmin extensions)
        if ($this->parseGarminRpt) {
            $this->xpath = new \DOMXPath($xmlObject);
            $this->xpath->registerNamespace('gpx', 'http://www.topografix.com/GPX/1/1');
            $this->xpath->registerNamespace('gpxx', 'http://www.garmin.com/xmlschemas/GpxExtensions/v3');
        }

        try {
            $geom = $this->geomFromXML($xmlObject);
        } catch (\Exception $e) {
            throw new \Exception("Cannot Read Geometry From GPX: " . $gpx . '<br>' . $e->getMessage());
        }

        return $geom;
    }

    /**
     * Parses the GPX XML and returns a geometry
     * @param \DOMDocument $xmlObject
     * @return GeometryCollection|Geometry Returns the geometry representation of the GPX (@see GeoPHP::buildGeometry)
     */
    protected function geomFromXML($xmlObject): Geometry
    {
        /** @var Geometry[] $geometries */
        $geometries = array_merge(
            $this->parseWaypoints($xmlObject),
            $this->parseTracks($xmlObject),
            $this->parseRoutes($xmlObject)
        );

        if (isset($this->trackFromRoute)) {
            $trackFromRoute = new LineString($this->trackFromRoute);
            $trackFromRoute->setData('gpxType', 'track');
            $trackFromRoute->setData('type', 'planned route');
            $geometries[] = $trackFromRoute;
        }

        $geometry = GeoPHP::buildGeometry($geometries);
        if (in_array('metadata', $this->gpxTypes->get('gpxType')) && $xmlObject->getElementsByTagName('metadata')->length === 1) {
            $metadata = $this->parseNodeProperties(
                $xmlObject->getElementsByTagName('metadata')->item(0),
                $this->gpxTypes->get('metadataType')
            );
            if ($geometry->getData() !== null && $metadata !== null) {
                $geometry = new GeometryCollection([$geometry]);
            }
            $geometry->setData($metadata);
        }
        
        return GeoPHP::geometryReduce($geometry);
    }

    /**
     * @param \DOMNode $xml
     * @param string $nodeName
     * @return array<\DOMElement>
     */
    protected function childElements(\DOMNode $xml, string $nodeName = ''): array
    {
        $children = [];
        foreach ($xml->childNodes as $child) {
            if ($child->nodeName == $nodeName) {
                $children[] = $child;
            }
        }
        return $children;
    }

    /**
     * @param \DOMElement $node
     * @return Point
     */
    protected function parsePoint(\DOMElement $node): Point
    {
        $lat = null;
        $lon = null;
        
        if ($node->attributes !== null) {
            $lat = $node->attributes->getNamedItem("lat")->nodeValue;
            $lon = $node->attributes->getNamedItem("lon")->nodeValue;
        }
        
        $ele = $node->getElementsByTagName('ele');
        
        if ($this->withElevation && $ele->length) {
            $elevation = $ele->item(0)->nodeValue;
            $point = new Point($lon, $lat, $elevation <> 0 ? $elevation : null);
        } else {
            $point = new Point($lon, $lat);
        }
        $point->setData($this->parseNodeProperties($node, $this->gpxTypes->get($node->nodeName . 'Type')));
        if ($node->nodeName === 'rtept' && $this->parseGarminRpt) {
            $rpts = $this->xpath->query('.//gpx:extensions/gpxx:RoutePointExtension/gpxx:rpt', $node);
            if ($rpts !== false) {
                foreach ($rpts as $element) {
                    $this->trackFromRoute[] = $this->parsePoint($element);
                }
            }
        }
        return $point;
    }

    /**
     * @param \DOMDocument $xmlObject
     * @return Point[]
     */
    protected function parseWaypoints($xmlObject): array
    {
        if (!in_array('wpt', $this->gpxTypes->get('gpxType'))) {
            return [];
        }
        $points = [];
        $wptElements = $xmlObject->getElementsByTagName('wpt');
        foreach ($wptElements as $wpt) {
            $point = $this->parsePoint($wpt);
            $point->setData('gpxType', 'waypoint');
            $points[] = $point;
        }
        return $points;
    }

    /**
     * @param \DOMDocument $xmlObject
     * @return LineString[]|MultiLineString[]
     */
    protected function parseTracks($xmlObject): array
    {
        if (!in_array('trk', $this->gpxTypes->get('gpxType'))) {
            return [];
        }
        $tracks = [];
        $trkElements = $xmlObject->getElementsByTagName('trk');
        foreach ($trkElements as $trk) {
            $segments = [];
            /** @noinspection SpellCheckingInspection */
            foreach ($this->childElements($trk, 'trkseg') as $trkseg) {
                $points = [];
                /** @noinspection SpellCheckingInspection */
                foreach ($this->childElements($trkseg, 'trkpt') as $trkpt) {
                    $points[] = $this->parsePoint($trkpt);
                }
                // Avoids creating invalid LineString
                if (count($points) > 1) {
                    $segments[] = new LineString($points);
                }
            }
            if (!empty($segments)) {
                $track = count($segments) === 1 ? $segments[0] : new MultiLineString($segments);
                $track->setData($this->parseNodeProperties($trk, $this->gpxTypes->get('trkType')));
                $track->setData('gpxType', 'track');
                $tracks[] = $track;
            }
        }

        return $tracks;
    }

    /**
     * @param \DOMDocument $xmlObject
     * @return LineString[]
     */
    protected function parseRoutes($xmlObject): array
    {
        if (!in_array('rte', $this->gpxTypes->get('gpxType'))) {
            return [];
        }
        $lines = [];
        $rteElements = $xmlObject->getElementsByTagName('rte');
        foreach ($rteElements as $rte) {
            $points = [];
            /** @noinspection SpellCheckingInspection */
            foreach ($this->childElements($rte, 'rtept') as $routePoint) {
                /** @noinspection SpellCheckingInspection */
                $points[] = $this->parsePoint($routePoint);
            }
            $line = new LineString($points);
            $line->setData($this->parseNodeProperties($rte, $this->gpxTypes->get('rteType')));
            $line->setData('gpxType', 'route');
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * Parses a DOMNode and returns its content in a multidimensional associative array
     * eg: <wpt><name>Test</name><link href="example.com"><text>Example</text></link></wpt>
     * to: ['name' => 'Test', 'link' => ['text'] => 'Example', '@attributes' => ['href' => 'example.com']]
     *
     * @param \DOMNode $node
     * @param string[]|null $tagList
     * @return array<string>|string
     */
    protected function parseNodeProperties(\DOMNode $node, $tagList = null)
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->nodeValue;
        }
        
        $result = [];
        
        // add/parse properties from childs to result
        if ($node->hasChildNodes()) {
            $this->addChildNodeProperties($result, $node, $tagList);
        }
        
        // add attributes to result
        if ($node->hasAttributes()) {
            $this->addNodeAttributes($result, $node);
        }
        
        return $result;
    }
    
    /**
     *
     * @param array<string, mixed>|string $result
     * @param \DOMNode $node
     * @param string[]|null $tagList
     * @return void
     */
    private function addChildNodeProperties(&$result, \DOMNode $node, $tagList)
    {
        foreach ($node->childNodes as $childNode) {
            /** @var \DOMNode $childNode */
            if ($childNode->hasChildNodes()) {
                if ($tagList === null || in_array($childNode->nodeName, $tagList ?: [])) {
                    if ($node->firstChild->nodeName == $node->lastChild->nodeName && $node->childNodes->length > 1) {
                        $result[$childNode->nodeName][] = $this->parseNodeProperties($childNode);
                    } else {
                        $result[$childNode->nodeName] = $this->parseNodeProperties($childNode);
                    }
                }
            } elseif ($childNode->nodeType === 1 && in_array($childNode->nodeName, $tagList ?: [])) {
                // node is a DOMElement
                $result[$childNode->nodeName] = $this->parseNodeProperties($childNode);
            } elseif ($childNode->nodeType === 3) {
                // node is a DOMText
                $result = $childNode->nodeValue;
            }
        }
    }
    
    /**
     *
     * @param array<string, mixed>|string $result
     * @param \DOMNode $node
     * @return void
     */
    private function addNodeAttributes(&$result, \DOMNode $node)
    {
        if (is_string($result)) {
            // As of the GPX specification text node cannot have attributes, thus this never happens
            $result = ['#text' => $result];
        }
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            if ($attribute->name !== 'lat' && $attribute->name !== 'lon' && trim($attribute->value) !== '') {
                $attributes[$attribute->name] = trim($attribute->value);
            }
        }
        if (!empty($attributes)) {
            $result['@attributes'] = $attributes;
        }
    }
}
