<?php

namespace GeoPHP\Tests;

use \GeoPHP\GeoPHP;
use PHPUnit\Framework\TestCase;

class PlaceholdersTest extends TestCase
{

    /**
     * @return void
     */
    public function testPlaceholders()
    {
        foreach (scandir('tests/input') as $file) {
            $parts = explode('.', $file);
            if ($parts[0]) {
                $format = $parts[1];
                $value = file_get_contents('tests/input/' . $file);
                //echo "\nloading: " . $file . " for format: " . $format;
                $geometry = GeoPHP::load($value, $format);

                $placeholders = array(
                    array('name' => 'hasZ'),
                    array('name' => 'is3D'),
                    array('name' => 'isMeasured'),
                    array('name' => 'isEmpty'),
                    array('name' => 'coordinateDimension'),
                    array('name' => 'getZ'),
                    array('name' => 'getM'),
                );

                foreach ($placeholders as $method) {
                    $argument = null;
                    $method_name = $method['name'];
                    if (isset($method['argument'])) {
                        $argument = $method['argument'];
                    }

                    switch ($method_name) {
                        case 'hasZ':
                            if ($geometry->geometryType() == 'Point') {
                                $this->assertNotNull(
                                    $geometry->$method_name($argument),
                                    'Failed on ' . $method_name . ' (test file: ' . $file . ')'
                                );
                            }
                            if ($geometry->geometryType() == 'LineString') {
                                $this->assertNotNull(
                                    $geometry->$method_name($argument),
                                    'Failed on ' . $method_name . ' (test file: ' . $file . ')'
                                );
                            }
                            if ($geometry->geometryType() == 'MultiLineString') {
                                $this->assertNotNull(
                                    $geometry->$method_name($argument),
                                    'Failed on ' . $method_name . ' (test file: ' . $file . ')'
                                );
                            }
                            break;
                        case 'getM':
                        case 'getZ':
                        case 'coordinateDimension':
                        case 'isEmpty':
                        case 'isMeasured':
                        case 'is3D':
                    }
                }
            }
        }
    }
}
