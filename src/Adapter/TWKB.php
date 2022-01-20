<?php
/**
 * This file contains the BinaryReader class.
 * For more information see the class description below.
 *
 * @author Peter Bathory <peter.bathory@cartographia.hu>
 * @since 2016-02-18
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace GeoPHP\Adapter;

use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\Point;

/**
 * PHP Geometry <-> TWKB encoder/decoder
 *
 * "Tiny Well-known Binary is is a multi-purpose format for serializing vector geometry data into a byte buffer,
 * with an emphasis on minimizing size of the buffer."
 * @see https://github.com/TWKB/Specification/blob/master/twkb.md
 *
 * This implementation supports:
 * - reading and writing all geometry types (1-7)
 * - empty geometries
 * - extended precision (Z, M coordinates; custom precision)
 * Partially supports:
 * - bounding box: can read and write, but don't store readed boxes (API missing)
 * - size attribute: can read and write size attribute, but seeking is not supported
 * - ID list: can read and write, but API is completely missing
 */
class TWKB implements GeoAdapter
{
    use TWKBReader, TWKBWriter;

    /**
     * @var Point
     */
    protected $lastPoint;

    /**
     * @var array<string, int> mapping of geometry types to TWKB type codes
     */
    protected static $typeMap = [
        Geometry::POINT => 1,
        Geometry::LINESTRING => 2,
        Geometry::POLYGON => 3,
        Geometry::MULTI_POINT => 4,
        Geometry::MULTI_LINESTRING => 5,
        Geometry::MULTI_POLYGON => 6,
        Geometry::GEOMETRY_COLLECTION => 7
    ];
}
