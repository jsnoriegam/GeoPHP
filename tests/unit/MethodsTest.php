<?php

namespace GeoPHP\Tests;

use \GeoPHP\GeoPHP;
use PHPUnit\Framework\TestCase;

class MethodsTest extends TestCase
{

    /**
     * @return void
     */
    public function testMethods()
    {
        foreach (scandir('tests/input') as $file) {
            $parts = explode('.', $file);
            if ($parts[0]) {
                $format = $parts[1];
                $value = file_get_contents('tests/input/' . $file);
                //echo "\nloading: " . $file . " for format: " . $format;
                $geometry = GeoPHP::load($value, $format);

                $methods = [
                    ['name' => 'getArea'],
                    ['name' => 'boundary'],
                    ['name' => 'getBBox'],
                    ['name' => 'getCentroid'],
                    ['name' => 'getLength'],
                    ['name' => 'greatCircleLength'],
                    ['name' => 'haversineLength'],
                    ['name' => 'getY'],
                    ['name' => 'getX'],
                    ['name' => 'numGeometries'],
                    ['name' => 'geometryN', 'argument' => '1'],
                    ['name' => 'startPoint'],
                    ['name' => 'endPoint'],
//                    ['name' => 'isRing'],
                    ['name' => 'isClosed'],
                    ['name' => 'numPoints'],
                    ['name' => 'pointN', 'argument' => '1'],
                    ['name' => 'exteriorRing'],
                    ['name' => 'numInteriorRings'],
                    ['name' => 'interiorRingN', 'argument' => '1'],
                    ['name' => 'dimension'],
                    ['name' => 'geometryType'],
                    ['name' => 'getSRID'],
                    ['name' => 'setSRID', 'argument' => '4326']
                ];

                foreach ($methods as $method) {
                    $argument = null;
                    $method_name = $method['name'];
                    if (isset($method['argument'])) {
                        $argument = $method['argument'];
                    }

                    $this->methodsTester($geometry, $method_name, $argument, $file);
                }

                $this->methodsTesterWithGeos($geometry);
            }
        }
    }

    /**
     * @param \GeoPHP\Geometry\Geometry $geometry
     * @param string $method_name
     * @param string|null $argument
     * @param string $file
     * @return void
     */
    public function methodsTester($geometry, $method_name, $argument, $file)
    {
        if (!method_exists($geometry, $method_name)) {
            $this->fail("Method " . $method_name . '() doesn\'t exists.');
        }

        $failedOnMessage = 'Failed on ' . $method_name . ' (test file: ' . $file . ', geometry type: ' . $geometry->geometryType() . ')';
        
        switch ($method_name) {
            case 'getY':
            case 'getX':
                if (!$geometry->isEmpty()) {
                    if ($geometry->geometryType() == 'Point') {
                        $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                    }
                    if ($geometry->geometryType() == 'LineString') {
                        $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                    }
                    if ($geometry->geometryType() == 'MultiLineString') {
                        $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                    }
                }
                break;
            case 'geometryN':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'startPoint':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'endPoint':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'isRing':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'isClosed':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'pointN':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'exteriorRing':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'numInteriorRings':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'interiorRingN':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'getSRID':
                break;
            case 'getBBox':
                if (!$geometry->isEmpty()) {
                    if ($geometry->geometryType() == 'Point') {
                        $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                    }
                    if ($geometry->geometryType() == 'LineString') {
                        $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                    }
                    if ($geometry->geometryType() == 'MultiLineString') {
                        $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                    }
                }
                break;
            case 'getCentroid':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'getLength':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertEquals($geometry->$method_name($argument), 0, $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotEquals($geometry->$method_name($argument), 0, $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotEquals($geometry->$method_name($argument), 0, $failedOnMessage);
                }
                break;
            case 'numGeometries':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'numPoints':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'dimension':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'boundary':
                if ($geometry->geometryType() == 'Point') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'LineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                if ($geometry->geometryType() == 'MultiLineString') {
                    $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                }
                break;
            case 'haversineLength':
                //TODO: Check if output is a float >= 0.
                //TODO: Sometimes haversineLength() returns NAN, needs to check why.
                break;
            case 'greatCircleLength':
                $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                break;
            case 'getArea':
                $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                break;
            case 'geometryType':
                $this->assertNotNull($geometry->$method_name($argument), $failedOnMessage);
                break;
            case 'setSRID':
                //TODO: The method setSRID() should return TRUE.
                break;
            default:
                $this->assertTrue($geometry->$method_name($argument), $failedOnMessage);
        }
    }

    /**
     * @param \GeoPHP\Geometry\Geometry $geometry
     * @throws \Exception
     * @return void
     */
    public function methodsTesterWithGeos($geometry)
    {
        // Cannot test methods if GEOS is not intstalled
        if (!GeoPHP::geosInstalled()) {
            return;
        }

        $methods = array(
            //'boundary', //@@TODO: Uncomment this and fix errors
            'envelope', //@@TODO: Testing reveales errors in this method -- POINT vs. POLYGON
            'getBBox',
            'x',
            'y',
            'startPoint',
            'endPoint',
            'isRing',
            'isClosed',
            'numPoints',
        );

        foreach ($methods as $method) {
            // Turn GEOS on
            GeoPHP::geosInstalled(true);
            $geos_result = $geometry->$method();

            // Turn GEOS off
            GeoPHP::geosInstalled(false);
            $norm_result = $geometry->$method();

            // Turn GEOS back On
            GeoPHP::geosInstalled(true);

            $geos_type = gettype($geos_result);
            $norm_type = gettype($norm_result);

            if ($geos_type != $norm_type) {
                var_dump($geos_type, $norm_type);
                $this->fail('Type mismatch on ' . $method);
            }

            // Now check base on type
            if ($geos_type == 'object') {
                $haus_dist = $geos_result->hausdorffDistance(GeoPHP::load($norm_result->out('wkt'), 'wkt'));

                // Get the length of the diagonal of the bbox - this is used to scale the haustorff distance
                // Using Pythagorean theorem
                $bb = $geos_result->getBBox();
                $scale = sqrt((($bb['maxy'] - $bb['miny']) ^ 2) + (($bb['maxx'] - $bb['minx']) ^ 2));

                // The difference in the output of GEOS and native-PHP methods should be less than 0.5 scaled haustorff units
                if ($haus_dist / $scale > 0.5) {
                    var_dump('GEOS : ', $geos_result->out('wkt'), 'NORM : ', $norm_result->out('wkt'));
                    $this->fail('Output mismatch on ' . $method);
                }
            }

            if ($geos_type == 'boolean' || $geos_type == 'string') {
                if ($geos_result !== $norm_result) {
                    var_dump('GEOS : ', $geos_result->out('wkt'), 'NORM : ', $norm_result->out('wkt'));
                    $this->fail('Output mismatch on ' . $method);
                }
            }

            //@@TODO: Run tests for output of types arrays and float
            //@@TODO: centroid function is non-compliant for collections and strings
        }
    }
}
