<?php
namespace geoPHP\Adapter;

use geoPHP\Geometry\Geometry;
use geoPHP\Geometry\GeometryCollection;
use geoPHP\Geometry\Point;
use geoPHP\Geometry\MultiPoint;
use geoPHP\Geometry\LineString;
use geoPHP\Geometry\Polygon;
use geoPHP\Geometry\MultiPolygon;

/*
 * (c) Camptocamp <info@camptocamp.com>
 * (c) Patrick Hayes
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PHP Google Geocoder Adapter
 *
 *
 * @package    geoPHP
 * @author     Patrick Hayes <patrick.d.hayes@gmail.com>
 */
class GoogleGeocode implements GeoAdapter
{

    /** @var \stdClass $result */
    protected $result;

    /**
     * Makes a geocoding (lat/lon lookup) with an address string or array geometry objects
     *
     * @param string $address Address to geocode
     * @param string $apiKey Your application's Google Maps Geocoding API key
     * @param string $returnType Type of Geometry to return. Can either be 'points' or 'bounds' (polygon)
     * @param array|bool|Geometry $bounds Limit the search area to within this region.
     *        For example by default geocoding "Cairo" will return the location of Cairo Egypt.
     *        If you pass a polygon of Illinois, it will return Cairo IL.
     * @param boolean $returnMultiple - Return all results in a multipoint or multipolygon
     *
     * @return Geometry
     * @throws \Exception If geocoding fails
     */
    public function read(
        string $address,
        $apiKey = null,
        string $returnType = 'point',
        bool $bounds = false,
        bool $returnMultiple = false
    ): Geometry {
        if (is_array($address)) {
            $address = join(',', $address);
        }

        if (gettype($bounds) === 'object') {
            $bounds = $bounds->getBBox();
        }
        if (gettype($bounds) === 'array') {
            $boundsString = '&bounds=' . $bounds['miny'] . ',' . $bounds['minx'] . '|' . $bounds['maxy'] . ',' . $bounds['maxx'];
        } else {
            $boundsString = '';
        }

        $url = "http://maps.googleapis.com/maps/api/geocode/json";
        $url .= '?address=' . urlencode($address);
        $url .= $boundsString . ($apiKey ? '&key=' . $apiKey : '');
        $this->result = json_decode(file_get_contents($url));

        if ($this->result->status === 'OK') {
            if (!$returnMultiple) {
                if ($returnType === 'point') {
                    return $this->getPoint();
                }
                if ($returnType === 'bounds' || $returnType === 'polygon') {
                    return $this->getPolygon();
                }
            } else {
                if ($returnType === 'point') {
                    $points = [];
                    foreach ($this->result->results as $delta => $item) {
                        $points[] = $this->getPoint($delta);
                    }
                    return new MultiPoint($points);
                }
                if ($returnType === 'bounds' || $returnType === 'polygon') {
                    $polygons = [];
                    foreach ($this->result->results as $delta => $item) {
                        $polygons[] = $this->getPolygon($delta);
                    }
                    return new MultiPolygon($polygons);
                }
            }
        } elseif ($this->result->status === 'ZERO_RESULTS') {
            return new Point;
        }
        
        if ($this->result->status) {
            throw new \Exception(
                'Error in Google Reverse Geocoder: '
                . $this->result->status
                . (isset($this->result->error_message) ? '. ' . $this->result->error_message : '')
            );
        }
        
        throw new \Exception('Unknown error in Google Reverse Geocoder');
    }

    /**
     * Makes a Reverse Geocoding (address lookup) with the (center) point of Geometry
     * Detailed documentation of response values can be found in:
     *
     * @see https://developers.google.com/maps/documentation/geocoding/intro#ReverseGeocoding
     *
     * @param Geometry $geometry
     * @param string $apiKey Your application's Google Maps Geocoding API key
     * @param string $language The language in which to return results. If not set, geocoder tries to use the native language of the domain.
     *
     * @return string|Object[] A formatted address or array of address components
     * @throws \Exception If geocoding fails
     */
    public function write(Geometry $geometry, $apiKey = null, $language = null): string
    {
        $centroid = $geometry->getCentroid();
        $lat = $centroid->getY();
        $lon = $centroid->getX();

        $url = "http://maps.googleapis.com/maps/api/geocode/json";
        /** @noinspection SpellCheckingInspection */
        $url .= '?latlng=' . $lat . ',' . $lon;
        $url .= ($language ? '&language=' . $language : '');
        $url .= ($apiKey ? '&key=' . $apiKey : '');

        $this->result = json_decode(file_get_contents($url));

        if ($this->result->status === 'OK') {
            return $this->result->results[0]->formatted_address;
        } elseif ($this->result->status === 'ZERO_RESULTS') {
            return '';
        }
        
        if ($this->result->status) {
            throw new \Exception(
                'Error in Google Reverse Geocoder: '
                . $this->result->status
                . (isset($this->result->error_message) ? '. ' . $this->result->error_message : '')
            );
        }
        
        throw new \Exception('Unknown error in Google Reverse Geocoder');
    }

    /**
     * @param int $delta
     * @return Point
     */
    private function getPoint(int $delta = 0): Point
    {
        $lat = $this->result->results[$delta]->geometry->location->lat;
        $lon = $this->result->results[$delta]->geometry->location->lng;
        return new Point($lon, $lat);
    }

    /**
     * @param int $delta
     * @return Polygon
     */
    private function getPolygon(int $delta = 0): Polygon
    {
        $points = [
            $this->getTopLeft($delta),
            $this->getTopRight($delta),
            $this->getBottomRight($delta),
            $this->getBottomLeft($delta),
            $this->getTopLeft($delta),
        ];
        $outerRing = new LineString($points);
        return new Polygon([$outerRing]);
    }

    /**
     * @param int $delta
     * @return Point
     */
    private function getTopLeft(int $delta = 0): Point
    {
        $lat = $this->result->results[$delta]->geometry->bounds->northeast->lat;
        $lon = $this->result->results[$delta]->geometry->bounds->southwest->lng;
        return new Point($lon, $lat);
    }

    /**
     * @param int $delta
     * @return Point
     */
    private function getTopRight(int $delta = 0): Point
    {
        $lat = $this->result->results[$delta]->geometry->bounds->northeast->lat;
        $lon = $this->result->results[$delta]->geometry->bounds->northeast->lng;
        return new Point($lon, $lat);
    }

    /**
     * @param int $delta
     * @return Point
     */
    private function getBottomLeft(int $delta = 0): Point
    {
        $lat = $this->result->results[$delta]->geometry->bounds->southwest->lat;
        $lon = $this->result->results[$delta]->geometry->bounds->southwest->lng;
        return new Point($lon, $lat);
    }

    /**
     * @param int $delta
     * @return Point
     */
    private function getBottomRight(int $delta = 0): Point
    {
        $lat = $this->result->results[$delta]->geometry->bounds->southwest->lat;
        $lon = $this->result->results[$delta]->geometry->bounds->northeast->lng;
        return new Point($lon, $lat);
    }
}
