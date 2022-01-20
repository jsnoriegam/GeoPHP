<?php

require '../vendor/autoload.php';

use GeoPHP\GeoPHP;
use GeoPHP\Geometry\Geometry;

run_test();

/**
 * @return void
 */
function run_test()
{
    set_time_limit(0);

    $FailOnError = function ($error_level, $error_message, $error_file, $error_line, $error_context) {
        echo "$error_level: $error_message in $error_file on line $error_line\n";
        echo "\e[31m" . "FAIL" . "\e[39m\n";
        exit(1);
    };

    set_error_handler($FailOnError);

    header("Content-type: text");

    if (GeoPHP::geosInstalled()) {
        print "GEOS is installed.\n";
    } else {
        print "GEOS is not installed.\n";
    }

    $beVerbose = getenv("VERBOSE") == 1 || getopt('v');
    $start = microtime(true);
    foreach (scandir('./input') as $file) {
        $parts = explode('.', $file);
        if ($parts[0]) {
            $startFile = microtime(true);
            $format = $parts[1];
            $value = file_get_contents('./input/' . $file);
            print '-- Testing ' . $file . "\n";
            
            try {
                $geometry = GeoPHP::load($value, $format);
            } catch (\Exception $e) {
                 print "\e[33m\t" . $e->getMessage() . "\e[39m\n";
                 continue;
            }

            echo $beVerbose ? "---- Adapters\n" : '';
            testAdapters($geometry);
            echo $beVerbose ? "---- Methods\n" : '';
            testGeosMethods($geometry);
            echo $beVerbose ? "---- Geometry\n" : '';
            testGeometry($geometry);
            echo $beVerbose ? "---- Detection\n" : '';
            testDetection($value, $format);
            print '  ' . sprintf('%.3f', microtime(true) - $startFile) . " s\n";
        }
    }
    print "\nSuccessfully completed under " . sprintf('%.3f', microtime(true) - $start)
            . " seconds, using maximum " . sprintf('%.3f', memory_get_peak_usage() / 1024 / 1024) . " MB\n";
    print "\e[32m" . "PASS" . "\e[39m\n";
}

/**
 * @param \GeoPHP\Geometry\Geometry $geometry
 * @return void
 */
function testGeometry(Geometry $geometry)
{
    // Test common functions
    try {
        $geometry->getArea();
        $geometry->boundary();
        $geometry->envelope();
        $geometry->getBBox();
        $geometry->getCentroid();
        $geometry->getLength();
        $geometry->greatCircleLength();
        $geometry->haversineLength();
        $geometry->getX();
        $geometry->getY();
        $geometry->getZ();
        $geometry->getM();
        $geometry->numGeometries();
        $geometry->geometryN(1);
        $geometry->startPoint();
        $geometry->endPoint();
        $geometry->isRing();
        $geometry->isClosed();
        $geometry->numPoints();
        $geometry->pointN(1);
        $geometry->exteriorRing();
        $geometry->numInteriorRings();
        $geometry->interiorRingN(1);
        $geometry->coordinateDimension();
        $geometry->geometryType();
        $geometry->getSRID();
        $geometry->setSRID(4326);
        $geometry->hasZ();
        $geometry->isMeasured();
        $geometry->isEmpty();
        $geometry->coordinateDimension();
        $geometry->translate(1, 1, 1);
        $geometry->getGeos();
        $geometry->asText();
        $geometry->asBinary();
    } catch (\GeoPHP\Exception\UnsupportedMethodException $e) {
        if (getenv("VERBOSE") == 1 || getopt('v')) {
            print "\e[33m\t" . $e->getMessage() . "\e[39m\n";
        }
    }

    // GEOS only functions
    try {
        $geometry->isSimple();
        $geometry->contains($geometry);
        $geometry->distance($geometry);
        $geometry->overlaps($geometry);
        $geometry->getGeos();
        $geometry->setGeos($geometry->getGeos());
        $geometry->pointOnSurface();
        $geometry->equals($geometry);
        $geometry->equalsExact($geometry);
        $geometry->relate($geometry);
        $geometry->checkValidity();
        $geometry->buffer(10);
        $geometry->intersection($geometry);
        $geometry->convexHull();
        $geometry->difference($geometry);
        $geometry->symDifference($geometry);
        $geometry->union($geometry);
        $geometry->simplify(0); // @TODO: Adjust this once we can deal with empty geometries
        #$geometry->makeValid(); // not available within GEOS!
        #$geometry->buildArea(); // not available within GEOS!
        $geometry->disjoint($geometry);
        $geometry->touches($geometry);
        $geometry->intersects($geometry);
        $geometry->crosses($geometry);
        $geometry->within($geometry);
        $geometry->covers($geometry);
        $geometry->coveredBy($geometry);
        $geometry->hausdorffDistance($geometry);
        $geometry->delaunayTriangulation(1.0);
        $geometry->voronoiDiagram(1.0);
        $geometry->offsetCurve(1.0);
        $geometry->clipByRect(1, 1, 10, 10);
    } catch (\Exception $e) {
        if (getenv("VERBOSE") == 1 || getopt('v')) {
            print "\e[33m\t" . $e->getMessage() . "\e[39m\n";
        }
    }
}

/**
 * @param \GeoPHP\Geometry\Geometry $geometry
 * @return void
 */
function testAdapters(Geometry $geometry)
{
    $beVerbose = getenv("VERBOSE") == 1 || getopt('v');
    
    try {
        // Test adapter output and input. Do a round-trip and re-test
        foreach (GeoPHP::getAdapterMap() as $adapter_key => $adapter_class) {
            if ($adapter_key == 'google_geocode') {
                //Don't test google geocoder regularily. Uncomment to test
                continue;
            }

            echo $beVerbose ? "  " . $adapter_class . "\n" : '';

            $output = $geometry->out($adapter_key);
            if ($output) {
                $adapter_name = '\\GeoPHP\\Adapter\\' . $adapter_class;
                /** @var \GeoPHP\Adapter\GeoAdapter $adapter_loader */
                $adapter_loader = new $adapter_name();
                $testGeom1 = $adapter_loader->read($output);
                $testGeom2 = $adapter_loader->read($testGeom1->out($adapter_key));

                if ($testGeom1->out('wkt') != $testGeom2->out('wkt')) {
                    print "\e[33m" . "\tMismatched adapter output in " . $adapter_class . "\e[39m\n";
                }
            } else {
                print "\e[33m" . "\tEmpty output on " . $adapter_key . "\e[39m\n";
            }
        }

        // Test to make sure adapters work the same wether GEOS is turned ON or OFF.
        // Methods cannot be tested if GEOS is not intstalled.
        if (!GeoPHP::geosInstalled()) {
            return;
        }
        if ($beVerbose) {
            echo "Testing with GEOS\n";
        }
        foreach (GeoPHP::getAdapterMap() as $adapterKey => $adapterName) {
            if ($adapterKey == 'google_geocode') {
                //Don't test google geocoder regularily. Uncomment to test
                continue;
            }

            if ($beVerbose) {
                echo ' ' . $adapterName . "\n";
            }
        
        
            // Turn GEOS on
            GeoPHP::geosInstalled(true);
            
            $output = $geometry->out($adapterKey);
            #if ($output === false) {
            #    continue;
            #}

            $adapterClassPath = '\\GeoPHP\\Adapter\\' . $adapterName;
            /** @var \GeoPHP\Adapter\GeoAdapter $adapterObj */
            $adapterObj = new $adapterClassPath();

            $geosGeom = $adapterObj->read($output);

            // Turn GEOS off
            GeoPHP::geosInstalled(false);
            $phpGeom = $adapterObj->read($output);

            // Turn GEOS back On
            GeoPHP::geosInstalled(true);

            // Check to make sure that both give the same results with geos enabled and disabled
            if ($geosGeom->out('wkt') != $phpGeom->out('wkt')) {
                $f = fopen("input_data_$adapterName.txt", 'w+');
                fwrite($f, $output);
                fclose($f);
                $f = fopen("geos_geom_$adapterName.wkt", 'w+');
                fwrite($f, $geosGeom->out('wkt'));
                fclose($f);
                $f = fopen("php_geom_$adapterName.wkt", 'w+');
                fwrite($f, $phpGeom->out('wkt'));
                fclose($f);
                print "Mismatched adapter output between GEOS and NORM in " . $adapterName . ". See files.\n";
            }
        }
    } catch (\Exception $e) {
        if ($beVerbose) {
            print "\e[33m\t" . $e->getMessage() . "\e[39m\n";
        }
    }
}

/**
 * @param Geometry $geometry
 * @return void
 */
function testGeosMethods(Geometry $geometry)
{
    // Cannot test methods if GEOS is not intstalled
    if (!GeoPHP::geosInstalled()) {
        return;
    }

    $methods = [
        'boundary',
        'envelope',
        'getBoundingBox',
        'x',
        'y',
        'z',
        'm',
        'startPoint',
        'endPoint',
        'isRing',
        'isClosed',
        'numPoints',
        'centroid',
        'length',
        'isEmpty',
        'isSimple'
    ];

    $beVerbose = getenv("VERBOSE") == 1 || getopt('v');
    
    foreach ($methods as $method) {
        echo $beVerbose ? "    $method \n" : '';
        
        try {
            // Turn GEOS on
            GeoPHP::geosInstalled(true);
            /** @var \GeoPHP\Geometry\Geometry $geosResult */
            $geosResult = $geometry->$method();

            // Turn GEOS off
            GeoPHP::geosInstalled(false);

            /** @var \GeoPHP\Geometry\Geometry $normResult */
            $normResult = $geometry->$method();

            // Turn GEOS back On
            GeoPHP::geosInstalled(true);

            $geosType = gettype($geosResult);
            $normType = gettype($normResult);

            if ($geosType !== $normType) {
                print "\e[33m" . "Type mismatch on " . $method . "\e[39m\n";
                continue;
            }

            // Now check base on type
            if ($geosType === 'object') {
                if ($geosResult->isEmpty()) {
                    if (!$normResult->isEmpty()) {
                        print "\e[33m" . "Result mismatch on " . $method . "\e[39m\n";
                        print 'WKT : ' . $geometry->out('wkt') . "\n";
                        print 'GEOS : ' . (string) $geosResult->asText() . "\n";
                        print 'NORM : ' . (string) $normResult->asText() . "\n";
                    }
                    continue;
                }
                
                $haussDist = $geosResult->hausdorffDistance(GeoPHP::load($normResult->out('wkt'), 'wkt'));

                // Get the length of the diagonal of the bbox - this is used to scale the hausdorff distance
                // Using Pythagorean theorem
                $bbox = $geosResult->getBoundingBox();
//                if (empty($bbox)) {
//                    print 'Method: ' . $method . "\n";
//                    print 'Geometry : ' . $geometry->out('wkt') . "\n";
//                    print 'GEOS : ' . $geos_result->out('wkt') . "\n";
//                    print 'NORM : ' . $norm_result->out('wkt') . "\n";
//                }
                $scale = sqrt((($bbox['maxy'] - $bbox['miny']) ^ 2) + (($bbox['maxx'] - $bbox['minx']) ^ 2));

                // The difference in the output of GEOS and native-PHP methods should be less than 0.5 scaled hausdorff units
                if ($haussDist / $scale > 0.5) {
                    print "\e[33m" . "Output mismatch on " . $method . "\e[39m\n";
                    print 'WKT : ' . $geometry->out('wkt') . "\n";
                    print 'GEOS : ' . $geosResult->out('wkt') . "\n";
                    print 'NORM : ' . $normResult->out('wkt') . "\n";
                }
            }

            if ($geosType == 'boolean' || $geosType == 'string') {
                if ($geosResult !== $normResult) {
                    print "\e[33m" . "Output mismatch on " . $method . "\e[39m\n";
                    print 'WKT : ' . $geometry->out('wkt') . "\n";
                    print 'GEOS : ' . (string) $geosResult->asText() . "\n";
                    print 'NORM : ' . (string) $normResult->asText() . "\n";
                }
            }
        } catch (\Exception $e) {
            if (getenv("VERBOSE") == 1 || getopt('v')) {
                print "\e[33m\t" . $e->getMessage() . "\e[39m\n";
            }
        }

        //@@TODO: Run tests for output of types arrays and float
        //@@TODO: centroid function is non-compliant for collections and strings
    }
}

/**
 * @param string $value
 * @param string $format
 * @return void
 */
function testDetection(string $value, string $format)
{
    $detected = GeoPHP::detectFormat($value);
    if ($detected != $format) {
        if ($detected) {
            print 'detected as "' . $detected . "\" and not as \"$format\" !\n";
        } else {
            print "format not detected\n";
        }
    }
    // Make sure it loads using auto-detect
    GeoPHP::load($value);
}
