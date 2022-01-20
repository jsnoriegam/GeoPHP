<?php
namespace GeoPHP\Adapter;

use GeoPHP\GeoPHP;
use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\GeometryCollection;
use GeoPHP\Geometry\Point;
use GeoPHP\Geometry\MultiPoint;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\MultiLineString;
use GeoPHP\Geometry\Polygon;
use GeoPHP\Geometry\MultiPolygon;

/**
 * GeoJSON class : a geoJSON reader/writer.
 *
 * Note that it will always return a GeoJSON geometry. This
 * means that if you pass it a feature, it will return the
 * geometry of that feature strip everything else.
 */
class GeoJSON implements GeoAdapter
{

    /**
     * Given an object or a string, return a Geometry
     *
     * @param string $input The GeoJSON string or object
     * @return Geometry
     * @throws \Exception
     */
    public function read(string $input): Geometry
    {
        #if (is_string($input)) {
            $input = json_decode($input);
        #}
        if (!is_object($input)) {
            throw new \Exception('Invalid JSON');
        }
        if (!isset($input->type) || !is_string($input->type)) {
            throw new \Exception('Invalid GeoJSON');
        }

        /** @var \stdClass $input */
        return $this->parseJSONObjects($input);
    }
    
    /**
     * @param \stdClass $input stdClass
     * @return Geometry
     */
    private function parseJSONObjects(\stdClass $input): Geometry
    {
        // FeatureCollection
        if ($input->type === 'FeatureCollection') {
            $geometries = [];
            foreach ($input->features as $feature) {
                $geometries[] = $this->parseJSONObjects($feature);
            }
            return GeoPHP::buildGeometry($geometries);
        }

        // Feature
        if ($input->type === 'Feature') {
            return $this->geoJSONFeatureToGeometry($input);
        }

        // Geometry
        return $this->geoJSONObjectToGeometry($input);
    }

    /**
     * @param \stdClass $input
     * @return int|null
     */
    private function getSRID($input)
    {
        if (isset($input->crs->properties->name)) {
            $m = [];
            // parse CRS codes in forms "EPSG:1234" and "urn:ogc:def:crs:EPSG::1234"
            preg_match('#EPSG[:]+(\d+)#', $input->crs->properties->name, $m);
            return isset($m[1]) ? (int) $m[1] : null;
        }
        
        return null;
    }

    /**
     * @param \stdClass $obj
     * @return Geometry
     * @throws \Exception
     */
    private function geoJSONFeatureToGeometry($obj): Geometry
    {
        $geometry = $this->parseJSONObjects($obj->geometry);
        if (isset($obj->properties)) {
            foreach ($obj->properties as $property => $value) {
                $geometry->setData($property, $value);
            }
        }

        return $geometry;
    }

    /**
     * @param \stdClass $obj
     * @return Geometry
     * @throws \Exception
     */
    private function geoJSONObjectToGeometry($obj): Geometry
    {
        $type = $obj->type;

        if ($type === 'GeometryCollection') {
            return $this->geoJSONObjectToGeometryCollection($obj);
        }
        $method = 'arrayTo' . $type;
        /** @var GeometryCollection $geometry */
        $geometry = $this->$method($obj->coordinates);
        $geometry->setSRID($this->getSRID($obj));
        
        return $geometry;
    }

    /**
     * @param array<int|float> $coordinates Array of coordinates
     * @return Point
     */
    private function arrayToPoint(array $coordinates): Point
    {
        switch (count($coordinates)) {
            case 2:
                return new Point($coordinates[0], $coordinates[1]);
            case 3:
                return new Point($coordinates[0], $coordinates[1], $coordinates[2]);
            case 4:
                return new Point($coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3]);
            default:
                return new Point();
        }
    }

    /**
     * @param array<array> $array
     * @return LineString
     */
    private function arrayToLineString(array $array): LineString
    {
        $points = [];
        foreach ($array as $componentArray) {
            $points[] = $this->arrayToPoint($componentArray);
        }
        return new LineString($points);
    }

    /**
     * @param array<array> $array
     * @return Polygon
     */
    private function arrayToPolygon(array $array): Polygon
    {
        $lines = [];
        foreach ($array as $componentArray) {
            $lines[] = $this->arrayToLineString($componentArray);
        }
        return new Polygon($lines);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param array<array> $array
     * @return MultiPoint
     */
    private function arrayToMultiPoint(array $array): MultiPoint
    {
        $points = [];
        foreach ($array as $componentArray) {
            $points[] = $this->arrayToPoint($componentArray);
        }
        return new MultiPoint($points);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param array<array> $array
     * @return MultiLineString
     */
    private function arrayToMultiLineString(array $array): MultiLineString
    {
        $lines = [];
        foreach ($array as $componentArray) {
            $lines[] = $this->arrayToLineString($componentArray);
        }
        return new MultiLineString($lines);
    }

    /**
     * @noinspection PhpUnusedPrivateMethodInspection
     * @param array<array> $array
     * @return MultiPolygon
     */
    private function arrayToMultiPolygon(array $array): MultiPolygon
    {
        $polygons = [];
        foreach ($array as $componentArray) {
            $polygons[] = $this->arrayToPolygon($componentArray);
        }
        return new MultiPolygon($polygons);
    }

    /**
     * @param \stdClass $obj
     * @throws \Exception
     * @return GeometryCollection
     */
    private function geoJSONObjectToGeometryCollection($obj): GeometryCollection
    {
        if (!isset($obj->geometries) || !is_array($obj->geometries)) {
            throw new \Exception('Invalid GeoJSON: GeometryCollection with no component geometries.');
        }
        
        $geometries = [];
        foreach ($obj->geometries as $componentObject) {
            $geometries[] = $this->geoJSONObjectToGeometry($componentObject);
        }
        
        $collection = new GeometryCollection($geometries);
        $collection->setSRID($this->getSRID($obj));
        
        return $collection;
    }

    /**
     * Serializes an object into a geojson string
     *
     * @param Geometry $geometry The object to serialize
     * @return string The GeoJSON string
     */
    public function write(Geometry $geometry): string
    {
        $response = json_encode($this->getArray($geometry));
        return $response ? $response : '';
    }

    /**
     * Creates a geoJSON array
     *
     * If the root geometry is a GeometryCollection and any of its geometries has data,
     * the root element will be a FeatureCollection with Feature elements (with the data).
     * If the root geometry has data, it will be included in a Feature object that contains the data.
     *
     * The geometry should have geographical coordinates since CRS support has been removed from from geoJSON specification (RFC 7946)
     * The geometry should'nt be measured, since geoJSON specification (RFC 7946) only supports the dimensional positions.
     *
     * @param Geometry|GeometryCollection $geometry
     * @param bool $isRoot Is geometry the root geometry?
     * @return array<string, mixed>
     */
    public function getArray(Geometry $geometry, bool $isRoot = true): array
    {
        if ($geometry->geometryType() === Geometry::GEOMETRY_COLLECTION) {
            $components = [];
            $isFeatureCollection = false;
            foreach ($geometry->getComponents() as $component) {
                if ($component->getData() !== null) {
                    $isFeatureCollection = true;
                }
                $components[] = $this->getArray($component, false);
            }
            if (!$isFeatureCollection || !$isRoot) {
                return [
                    'type' => 'GeometryCollection',
                    'geometries' => $components
                ];
            } else {
                $features = [];
                foreach ($geometry->getComponents() as $i => $component) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => $component->getData(),
                        'geometry' => $components[$i],
                    ];
                }
                return [
                    'type' => 'FeatureCollection',
                    'features' => $features
                ];
            }
        }

        if ($isRoot && $geometry->getData() !== null) {
            return [
                'type' => 'Feature',
                'properties' => $geometry->getData(),
                'geometry' => [
                    'type' => $geometry->geometryType(),
                    'coordinates' => $geometry->isEmpty() ? [] : $geometry->asArray()
                ]
            ];
        }
        $object = [
            'type' => $geometry->geometryType(),
            'coordinates' => $geometry->isEmpty() ? [] : $geometry->asArray()
        ];
        return $object;
    }
}
