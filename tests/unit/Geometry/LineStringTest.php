<?php

namespace GeoPHP\Tests\Geometry;

use GeoPHP\Exception\InvalidGeometryException;
use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\Point;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests of LineString geometry
 *
 * @group geometry
 *
 */
class LineStringTest extends TestCase
{
    
    /**
     *
     * @param string|null $name
     * @param array<mixed> $data
     * @param string $dataName
     * @return void
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    /**
     * @param array[] $coordinateArray
     * @return Point[]
     */
    private function createPoints($coordinateArray)
    {
        $points = [];
        foreach ($coordinateArray as $point) {
            $points[] = Point::fromArray($point);
        }
        return $points;
    }

    /**
     * @return array<string, array>
     */
    public function providerValidComponents()
    {
        return [
            'empty' =>
                [[]],
            'with two points' =>
                [[[0, 0], [1, 1]]],
            'LineString Z' =>
                [[[0, 0, 0], [1, 1, 1]]],
            'LineString M' =>
                [[[0, 0, null, 0], [1, 1, null, 1]]],
            'LineString ZM' =>
                [[[0, 0, 0, 0], [1, 1, 1, 1]]],
            'LineString with 5 points' =>
                [[[0, 0], [1, 1], [2, 2], [3, 3], [4, 4]]],
        ];
    }

    /**
     * @dataProvider providerValidComponents
     *
     * @param array[] $points
     * @return void
     */
    public function testConstructor(array $points)
    {
        parent::assertNotNull(new LineString($this->createPoints($points)));
    }

    /**
     * @return void
     */
    public function testConstructorEmptyComponentThrowsException()
    {
        $this->expectException(InvalidGeometryException::class);
        
        if (method_exists($this, "expectExceptionMessageMatches")) {
            $this->expectExceptionMessageMatches('/Cannot create a collection of empty Points.+/');
        }

        // Empty points
        new LineString([new Point(), new Point(), new Point()]);
    }

    /**
     * @return void
     */
    public function testConstructorNonArrayComponentThrowsException()
    {
        $this->expectException(\TypeError::class);
        
        if (method_exists($this, "expectExceptionMessageMatches")) {
            $this->expectExceptionMessageMatches('/must be of (the )*type array, string given/');
        }
        
        /* @phpstan-ignore-next-line */
        new LineString('foo');
    }

    /**
     * @return void
     */
    public function testConstructorSinglePointThrowsException()
    {
        $this->expectException(InvalidGeometryException::class);
        
        if (method_exists($this, "expectExceptionMessageMatches")) {
            $this->expectExceptionMessageMatches('/Cannot construct a [a-zA-Z_\\\\]+LineString with a single point/');
        }

        new LineString([new Point(1, 2)]);
    }

    /**
     * @return void
     */
    public function testConstructorWrongComponentTypeThrowsException()
    {
        $this->expectException(InvalidGeometryException::class);
        
        if (method_exists($this, "expectExceptionMessageMatches")) {
            $this->expectExceptionMessageMatches('/Cannot create a collection of [a-zA-Z_\\\\]+ components, expected type is.+/');
        }

        new LineString([new LineString(), new LineString()]);
    }

    /**
     * @return void
     */
    public function testFromArray()
    {
        parent::assertEquals(
            LineString::fromArray([[1,2,3,4], [5,6,7,8]]),
            new LineString([new Point(1, 2, 3, 4), new Point(5, 6, 7, 8)])
        );
    }

    /**
     * @return void
     */
    public function testGeometryType()
    {
        $line = new LineString();

        parent::assertEquals(LineString::LINESTRING, $line->geometryType());

        parent::assertInstanceOf(LineString::class, $line);
        parent::assertInstanceOf(\GeoPHP\Geometry\Curve::class, $line);
        parent::assertInstanceOf(\GeoPHP\Geometry\Collection::class, $line);
        parent::assertInstanceOf(\GeoPHP\Geometry\Geometry::class, $line);
    }

    /**
     * @return void
     */
    public function testIsEmpty()
    {
        $line1 = new LineString();
        parent::assertTrue($line1->isEmpty());

        $line2 = new LineString($this->createPoints([[1,2], [3,4]]));
        parent::assertFalse($line2->isEmpty());
    }

    /**
     * @return void
     */
    public function testDimension()
    {
        parent::assertSame((new LineString())->dimension(), 1);
    }

    /**
     * @dataProvider providerValidComponents
     *
     * @param array[] $points
     * @return void
     */
    public function testNumPoints($points)
    {
        $line = new LineString($this->createPoints($points));
        parent::assertCount($line->numPoints(), $points);
    }

    /**
     * @dataProvider providerValidComponents
     *
     * @param array[] $points
     * @return void
     */
    public function testPointN($points)
    {
        $components = $this->createPoints($points);
        $line = new LineString($components);

        parent::assertNull($line->pointN(0));

        for ($i=1; $i < count($components); $i++) {
            // positive n
            parent::assertEquals($components[$i-1], $line->pointN($i));

            // negative n
            parent::assertEquals($components[count($components)-$i], $line->pointN(-$i));
        }
    }

    /**
     * @return array<string, array>
     */
    public function providerCentroid()
    {
        return [
            'empty LineString' => [[], new Point()],
            'null coordinates' => [[[0, 0], [0, 0]], new Point(0, 0)],
            '↗ vector' => [[[0, 0], [1, 1]], new Point(0.5, 0.5)],
            '↙ vector' => [[[0, 0], [-1, -1]], new Point(-0.5, -0.5)],
            'random geographical coordinates' => [[
                    [20.0390625, -16.97274101999901],
                    [-11.953125, 17.308687886770034],
                    [0.703125, 52.696361078274485],
                    [30.585937499999996, 52.696361078274485],
                    [42.5390625, 41.77131167976407],
                    [-13.359375, 38.8225909761771],
                    [18.984375, 17.644022027872726]
            ], new Point(8.71798087550578, 31.1304531386738)],
            'crossing the antimeridian' => [[[170, 47], [-170, 47]], new Point(0, 47)]
        ];
    }

    /**
     * @dataProvider providerCentroid
     *
     * @param array[] $points
     * @param Point $centroidPoint
     * @return void
     */
    public function testCentroid($points, $centroidPoint)
    {
        $line = LineString::fromArray($points);
        $centroid = $line->centroid();
        $centroid->setGeos(null);

        parent::assertEquals($centroidPoint, $centroid);
    }

    /**
     * @return array<string, array>
     */
    public function providerIsSimple()
    {
        return [
                'simple' =>
                    [[[0, 0], [0, 10]], true],
                'self-crossing' =>
                    [[[0, 0], [10, 0], [10, 10], [0, -10]], false],
//                'self-tangent' =>
//                    [[[0, 0], [10, 0], [-10, 0]], false],
            // FIXME: isSimple() fails to check self-tangency
        ];
    }

    /**
     * @dataProvider providerIsSimple
     *
     * @param array[] $points
     * @param bool  $result
     * @return void
     */
    public function testIsSimple($points, $result)
    {
        $line = LineString::fromArray($points);

        parent::assertSame($line->isSimple(), $result);
    }

    /**
     * @return array[]
     */
    public function providerLength()
    {
        return [
                [[[0, 0], [10, 0]], 10.0],
                [[[1, 1], [2, 2], [2, 3.5], [1, 3], [1, 2], [2, 1]], 6.44646111349608],
        ];
    }

    /**
     * @dataProvider providerLength
     *
     * @param array[] $points
     * @param float $result
     * @return void
     */
    public function testLength($points, $result)
    {
        $line = LineString::fromArray($points);
        parent::assertSame($line->length(), $result);
    }

    /**
     * @return array[]
     */
    public function providerLength3D()
    {
        return [
                [[[0, 0, 0], [10, 0, 10]], 14.142135623731],
                [[[1, 1, 0], [2, 2, 2], [2, 3.5, 0], [1, 3, 2], [1, 2, 0], [2, 1, 2]], 11.926335310544],
        ];
    }

    /**
     * @dataProvider providerLength3D
     *
     * @param array[] $points
     * @param float $result
     * @return void
     */
    public function testLength3D($points, $result)
    {
        $line = LineString::fromArray($points);
        parent::assertSame($line->length3D(), $result);
    }

    /**
     * @return array[]
     */
    public function providerLengths()
    {
        return [
                [[[0, 0], [0, 0]], [
                        'greatCircle' => 0.0,
                        'haversine'   => 0.0,
                        'vincenty'    => 0.0,
                        'PostGIS'     => 0.0
                ]],
                [[[0, 0], [10, 0]], [
                        'greatCircle' => 1113194.9079327357,
                        'haversine'   => 1113194.9079327371,
                        'vincenty'    => 1113194.9079322326,
                        'PostGIS'     => 1113194.90793274
                ]],
                [[[0, 0, 0], [10, 0, 5000]], [
                        'greatCircle' => 1113206.136817154,
                        'haversine'   => 1113194.9079327371,
                        'vincenty'    => 1113194.9079322326,
                        'PostGIS'     => 1113194.90793274
                ]],
                [[[0, 47], [10, 47]], [
                        'greatCircle' => 758681.06593496865,
                        'haversine'   => 758681.06593497901,
                        'vincenty'    => 760043.0186457854,
                        'postGIS'     => 760043.018642104
                ]],
                [[[1, 1, 0], [2, 2, 2], [2, 3.5, 0], [1, 3, 2], [1, 2, 0], [2, 1, 2]], [
                        'greatCircle' => 717400.38999229996,
                        'haversine'   => 717400.38992081373,
                        'vincenty'    => 714328.06433538091,
                        'postGIS'     => 714328.064406871
                ]],
                [[[19, 47], [19.000001, 47], [19.000001, 47.000001], [19.000001, 47.000002], [19.000002, 47.000002]], [
                        'greatCircle' => 0.37447839912084818,
                        'haversine'   => 0.36386002147417207,
                        'vincenty'    => 0.37445330532190713,
                        'postGIS'     => 0.374453678675281
                ]]
        ];
    }

    /**
     * @dataProvider providerLengths
     *
     * @param array[] $points
     * @param array<string, int|float> $results
     * @return void
     */
    public function testGreatCircleLength($points, $results)
    {
        $line = LineString::fromArray($points);

        if (method_exists($this, "assertEqualsWithDelta")) {
            parent::assertEqualsWithDelta($line->greatCircleLength(), $results['greatCircle'], 1e-8);
        }
    }

    /**
     * @dataProvider providerLengths
     *
     * @param array[] $points
     * @param array<string, int|float> $results
     * @return void
     */
    public function testHaversineLength($points, $results)
    {
        $line = LineString::fromArray($points);

        if (method_exists($this, "assertEqualsWithDelta")) {
            parent::assertEqualsWithDelta($line->haversineLength(), $results['haversine'], 1e-7);
        }
    }

    /**
     * @dataProvider providerLengths
     *
     * @param array[] $points
     * @param array<string, int|float> $results
     * @return void
     */
    public function testVincentyLength($points, $results)
    {
        $line = LineString::fromArray($points);

        if (method_exists($this, "assertEqualsWithDelta")) {
            parent::assertEqualsWithDelta($line->vincentyLength(), $results['vincenty'], 1e-8);
        }
    }

    /**
     * @return void
     */
    public function testVincentyLengthAntipodalPoints()
    {
        if (method_exists($this, "assertIsFloat")) {
            $line = LineString::fromArray([[-89.7, 0], [89.7, 0]]);
            parent::assertIsFloat($line->vincentyLength());
        }
    }

    /**
     * @return void
     */
    public function testExplode()
    {
        $point1 = new Point(1, 2);
        $point2 = new Point(3, 4);
        $point3 = new Point(5, 6);
        $line = new LineString([$point1, $point2, $point3]);

        parent::assertEquals($line->explode(), [new LineString([$point1, $point2]), new LineString([$point2, $point3])]);
        parent::assertSame($line->explode(true), [[$point1, $point2], [$point2, $point3]]);
        parent::assertSame((new LineString())->explode(), []);
        parent::assertSame((new LineString())->explode(true), []);
    }

    /**
     *
     * @return array<string, array>
     */
    public function providerDistance()
    {
        return [
            'Point on vertex' =>
                [new Point(0, 10), 0.0],
            'Point, closest distance is 10' =>
                [new Point(10, 10), 10.0],
            'LineString, same points' =>
                [LineString::fromArray([[0, 10], [10, 10]]), 0.0],
            'LineString, closest distance is 10' =>
                [LineString::fromArray([[10, 10], [20, 20]]), 10.0],
            'intersecting line' =>
                [LineString::fromArray([[-10, 5], [10, 5]]), 0.0],
            'GeometryCollection' =>
                [new \GeoPHP\Geometry\GeometryCollection([LineString::fromArray([[10, 10], [20, 20]])]), 10.0],
            // TODO: test other types
        ];
    }

    /**
     * @dataProvider providerDistance
     *
     * @param Geometry $otherGeometry
     * @param float $expectedDistance
     * @return void
     */
    public function testDistance($otherGeometry, $expectedDistance)
    {
        $line = LineString::fromArray([[0, 0], [0, 10]]);
        parent::assertSame($line->distance($otherGeometry), $expectedDistance);
    }

    /**
     * @return void
     */
    public function testMinimumAndMaximumZAndMAndDifference()
    {
        $line = LineString::fromArray([[0, 0, 100.0, 0.0], [1, 1, 50.0, -0.5], [2, 2, 150.0, -1.0], [3, 3, 75.0, 0.5]]);

        parent::assertSame($line->minimumZ(), 50.0);
        parent::assertSame($line->maximumZ(), 150.0);

        parent::assertSame($line->minimumM(), -1.0);
        parent::assertSame($line->maximumM(), 0.5);

        parent::assertSame($line->zDifference(), 25.0);
        parent::assertSame(LineString::fromArray([[0, 1], [2, 3]])->zDifference(), null);
    }

    /**
     * @return array[] [tolerance, gain, loss]
     */
    public function providerElevationGainAndLossByTolerance()
    {
        return [
            [null, 50.0, 30.0],
            [0, 50.0, 30.0],
            [5, 48.0, 28.0],
            [15, 36.0, 16.0]
        ];
    }

    /**
     * @dataProvider providerElevationGainAndLossByTolerance
     *
     * @param float|null $tolerance
     * @param float $gain
     * @param float $loss
     * @return void
     */
    public function testElevationGainAndLoss($tolerance, $gain, $loss)
    {
        $line = LineString::fromArray(
            [[0, 0, 100], [0, 0, 102], [0, 0, 105], [0, 0, 103], [0, 0, 110], [0, 0, 118],
                [0, 0, 102], [0, 0, 108], [0, 0, 102], [0, 0, 108], [0, 0, 102], [0, 0, 120] ]
        );

        parent::assertSame($line->elevationGain($tolerance), $gain);
        parent::assertSame($line->elevationLoss($tolerance), $loss);
    }
}
