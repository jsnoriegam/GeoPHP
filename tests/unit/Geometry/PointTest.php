<?php

namespace GeoPHP\Tests\Geometry;

use GeoPHP\Exception\InvalidGeometryException;
use GeoPHP\Geometry\Geometry;
use GeoPHP\Geometry\GeometryCollection;
use GeoPHP\Geometry\LineString;
use GeoPHP\Geometry\MultiPoint;
use GeoPHP\Geometry\Point;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests of Point geometry
 *
 * @group geometry
 *
 */
class PointTest extends TestCase
{

    /**
     * @return array<string, array>
     */
    public function providerValidCoordinatesXY()
    {
        return [
            'null coordinates' => [0, 0],
            'positive integer' => [10, 20],
            'negative integer' => [-10, -20],
            'WGS84'            => [47.1234056789, 19.9876054321],
            'HD72/EOV'         => [238084.12, 649977.59],
        ];
    }

    /**
     * @dataProvider providerValidCoordinatesXY
     *
     * @param int|float $x
     * @param int|float $y
     * @return void
     */
    public function testValidCoordinatesXY($x, $y)
    {
        $point = new Point($x, $y);

        parent::assertEquals($x, $point->x());
        parent::assertEquals($y, $point->y());
        parent::assertNull($point->z());
        parent::assertNull($point->m());

//        parent::assertIsFloat($point->x());
//        parent::assertIsFloat($point->y());
        parent::assertTrue(is_float($point->x()));
        parent::assertTrue(is_float($point->y()));
    }

    /**
     * @return array<string, array>
     */
    public function providerValidCoordinatesXYZOrXYM()
    {
        return [
            'null coordinates' => [0, 0, 0],
            'positive integer' => [10, 20, 30],
            'negative integer' => [-10, -20, -30],
            'WGS84'            => [47.1234056789, 19.9876054321, 100.1],
            'HD72/EOV'         => [238084.12, 649977.59, 56.38],
        ];
    }

    /**
     * @dataProvider providerValidCoordinatesXYZOrXYM
     *
     * @param int|float $x
     * @param int|float $y
     * @param int|float $z
     * @return void
     */
    public function testValidCoordinatesXYZ($x, $y, $z)
    {
        $point = new Point($x, $y, $z);

        parent::assertEquals($x, $point->x());
        parent::assertEquals($y, $point->y());
        parent::assertEquals($z, $point->z());
        parent::assertNull($point->m());

//        parent::assertIsFloat($point->x());
//        parent::assertIsFloat($point->y());
//        parent::assertIsFloat($point->z());
        parent::assertTrue(is_float($point->x()));
        parent::assertTrue(is_float($point->y()));
        parent::assertTrue(is_float($point->z()));
    }

    /**
     * @dataProvider providerValidCoordinatesXYZOrXYM
     *
     * @param int|float $x
     * @param int|float $y
     * @param int|float $m
     * @return void
     */
    public function testValidCoordinatesXYM($x, $y, $m)
    {
        $point = new Point($x, $y, null, $m);

        parent::assertEquals($x, $point->x());
        parent::assertEquals($y, $point->y());
        parent::assertEquals($m, $point->m());
        parent::assertNull($point->z());

//        parent::assertIsFloat($point->x());
//        parent::assertIsFloat($point->y());
//        parent::assertIsFloat($point->m());
        parent::assertTrue(is_float($point->x()));
        parent::assertTrue(is_float($point->y()));
        parent::assertTrue(is_float($point->m()));
    }

    /**
     * @return array<string, array>
     */
    public function providerValidCoordinatesXYZM()
    {
        return [
            'null coordinates' => [0, 0, 0, 0],
            'positive integer' => [10, 20, 30, 40],
            'negative integer' => [-10, -20, -30, -40],
            'WGS84'            => [47.1234056789, 19.9876054321, 100.1, 0.00001],
            'HD72/EOV'         => [238084.12, 649977.59, 56.38, -0.00001],
        ];
    }

    /**
     * @dataProvider providerValidCoordinatesXYZM
     *
     * @param int|float $x
     * @param int|float $y
     * @param int|float $z
     * @param int|float $m
     * @return void
     */
    public function testValidCoordinatesXYZM($x, $y, $z, $m)
    {
        $point = new Point($x, $y, $z, $m);

        parent::assertEquals($x, $point->x());
        parent::assertEquals($y, $point->y());
        parent::assertEquals($z, $point->z());
        parent::assertEquals($m, $point->m());

//        parent::assertIsFloat($point->x());
//        parent::assertIsFloat($point->y());
//        parent::assertIsFloat($point->z());
//        parent::assertIsFloat($point->m());
        parent::assertTrue(is_float($point->x()));
        parent::assertTrue(is_float($point->y()));
        parent::assertTrue(is_float($point->z()));
        parent::assertTrue(is_float($point->m()));
    }

    /**
     * @return void
     */
    public function testConstructorWithoutParameters()
    {
        $point = new Point();

        parent::assertTrue($point->isEmpty());

        parent::assertNull($point->x());
        parent::assertNull($point->y());
        parent::assertNull($point->z());
        parent::assertNull($point->m());
    }

    /**
     * @return array<string, array>
     */
    public function providerEmpty()
    {
        return [
            'no coordinates'     => [],
            'x is null'          => [null, 20],
            'y is null'          => [10, null],
            'x and y is null'    => [null, null, 30],
            'x, y, z is null'    => [null, null, null, 40],
            'x, y, z, m is null' => [null, null, null, null],
        ];
    }

    /**
     * @dataProvider providerEmpty
     *
     * @param int|float|null $x
     * @param int|float|null $y
     * @param int|float|null $z
     * @param int|float|null $m
     * @return void
     */
    public function testEmpty($x = null, $y = null, $z = null, $m = null)
    {
        $point = new Point($x, $y, $z, $m);

        parent::assertTrue($point->isEmpty());

        parent::assertNull($point->x());
        parent::assertNull($point->y());
        parent::assertNull($point->z());
        parent::assertNull($point->m());
    }

    /**
     * @return array<string, array>
     */
    public function providerInvalidCoordinates()
    {
        return [
            'string coordinates'  => ['x', 'y'],
            'boolean coordinates' => [true, false],
            'z is string'         => [1, 2, 'z'],
            'm is string'         => [1, 2, 3, 'm'],
        ];
    }

    /**
     * @dataProvider providerInvalidCoordinates
     *
     * @param mixed $x
     * @param mixed $y
     * @param mixed $z
     * @param mixed $m
     * @return void
     */
    public function testConstructorWithInvalidCoordinates($x, $y, $z = null, $m = null)
    {
        $this->expectException(InvalidGeometryException::class);
        new Point($x, $y, $z, $m);
    }

    /**
     * @return void
     */
    public function testGeometryType()
    {
        $point = new Point();

        parent::assertEquals(\GeoPHP\Geometry\Geometry::POINT, $point->geometryType());

        parent::assertInstanceOf(Point::class, $point);
        parent::assertInstanceOf(\GeoPHP\Geometry\Geometry::class, $point);
    }

    /**
     * @return array<string, array>
     */
    public function providerIs3D()
    {
        return [
            '2 coordinates is not 3D'   => [false, 1, 2],
            '3 coordinates'             => [true, 1, 2, 3],
            '4 coordinates'             => [true, 1, 2, 3, 4],
            'x, y is null but z is not' => [false, null, null, 3, 4],
            'z is null'                 => [false, 1, 2, null, 4],
            'empty point'               => [false],
        ];
    }

    /**
     * @dataProvider providerIs3D
     * @param bool $result
     * @param mixed $x
     * @param mixed $y
     * @param mixed $z
     * @param mixed $m
     * @return void
     */
    public function testIs3D($result, $x = null, $y = null, $z = null, $m = null)
    {
        parent::assertSame($result, (new Point($x, $y, $z, $m))->is3D());
    }

    /**
     * @return array<string, array>
     */
    public function providerIsMeasured()
    {
        return [
            '2 coordinates is false'    => [false, 1, 2],
            '3 coordinates is false'    => [false, 1, 2, 3],
            '4 coordinates'             => [true, 1, 2, 3, 4],
            'x, y is null but m is not' => [false, null, null, 3, 4],
            'm is null'                 => [false, 1, 2, 3, null],
            'empty point'               => [false],
        ];
    }

    /**
     * @dataProvider providerIsMeasured
     * @param bool $result
     * @param mixed $x
     * @param mixed $y
     * @param mixed $z
     * @param mixed $m
     * @return void
     */
    public function testIsMeasured($result, $x = null, $y = null, $z = null, $m = null)
    {
        parent::assertSame($result, (new Point($x, $y, $z, $m))->isMeasured());
    }

    /**
     * @return void
     */
    public function testGetComponents()
    {
        $point = new Point(1, 2);
        $components = $point->getComponents();

        //parent::assertIsArray($components);
        parent::assertTrue(is_array($components));
        parent::assertCount(1, $components);
        parent::assertSame($point, $components[0]);
    }

    /**
     * @dataProvider providerValidCoordinatesXYZM
     *
     * @param int|float $x
     * @param int|float $y
     * @param int|float $z
     * @param int|float $m
     * @return void
     */
    public function testInvertXY($x, $y, $z, $m)
    {
        $point = new Point($x, $y, $z, $m);
        $originalPoint = clone $point;
        $point->invertXY();

        parent::assertEquals($x, $point->y());
        parent::assertEquals($y, $point->x());
        parent::assertEquals($z, $point->z());
        parent::assertEquals($m, $point->m());

        $point->invertXY();
        parent::assertEquals($point, $originalPoint);
    }

    /**
     * @return void
     */
    public function testCentroidIsThePointItself()
    {
        $point = new Point(1, 2, 3, 4);
        parent::assertSame($point, $point->centroid());
    }

    /**
     * @return void
     */
    public function testBBox()
    {
        $point = new Point(1, 2);
        parent::assertSame($point->getBBox(), [
                'maxy' => 2.0,
                'miny' => 2.0,
                'maxx' => 1.0,
                'minx' => 1.0,
        ]);
    }

    /**
     * @return void
     */
    public function testAsArray()
    {
        $pointAsArray = (new Point())->asArray();
        parent::assertCount(2, $pointAsArray);
        parent::assertNull($pointAsArray[0]);
        parent::assertNull($pointAsArray[1]);

        $pointAsArray = (new Point(1, 2))->asArray();
        parent::assertSame($pointAsArray, [1.0, 2.0]);

        $pointAsArray = (new Point(1, 2, 3))->asArray();
        parent::assertSame($pointAsArray, [1.0, 2.0, 3.0]);

        $pointAsArray = (new Point(1, 2, null, 3))->asArray();
        parent::assertSame($pointAsArray, [1.0, 2.0, null, 3.0]);

        $pointAsArray = (new Point(1, 2, 3, 4))->asArray();
        parent::assertSame($pointAsArray, [1.0, 2.0, 3.0, 4.0]);
    }

    /**
     * @return void
     */
    public function testBoundary()
    {
        parent::assertEquals((new Point(1, 2))->boundary(), new GeometryCollection());
    }

    /**
     * @return void
     */
    public function testEquals()
    {
        parent::assertTrue((new Point())->equals(new Point()));

        $point = new Point(1, 2, 3, 4);
        parent::assertTrue($point->equals(new Point(1, 2, 3, 4)));

        parent::assertTrue($point->equals(new Point(1.0000000001, 2.0000000001, 3, 4)));
        parent::assertTrue($point->equals(new Point(0.9999999999, 1.9999999999, 3, 4)));

        parent::assertFalse($point->equals(new Point(1.000000001, 2.000000001, 3, 4)));
        parent::assertFalse($point->equals(new Point(0.999999999, 1.999999999, 3, 4)));

        parent::assertFalse($point->equals(new GeometryCollection()));
    }

    /**
     * @return void
     */
    public function testFlatten()
    {
        $point = new Point(1, 2, 3, 4);
        $point->flatten();

        parent::assertEquals($point->x(), 1);
        parent::assertEquals($point->y(), 2);
        parent::assertNull($point->z());
        parent::assertNull($point->m());
        parent::assertFalse($point->is3D());
        parent::assertFalse($point->isMeasured());
    }

    /**
     * @return array<string, array>
     */
    public function providerDistance()
    {
        return [
            'empty Point' =>
                [new Point(), null],
            'Point x+10' =>
                [new Point(10, 0), 10.0],
            'Point y+10' =>
                [new Point(0, 10), 10.0],
            'Point x+10,y+10' =>
                [new Point(10, 10), 14.142135623730951],
            'LineString, point is a vertex' =>
                [LineString::fromArray([[-10, 10], [0, 0], [10, 10]]), 0.0],
            'LineString, containing a vertex twice' =>
                [LineString::fromArray([[0, 10], [0, 10]]), 10.0],
            'LineString, point on line' =>
                [LineString::fromArray([[-10, -10], [10, 10]]), 0.0],
            'MultiPoint, closest distance is 0' =>
                [MultiPoint::fromArray([[0, 0], [10, 20]]), 0.0],
            'MultiPoint, closest distance is 10' =>
                [MultiPoint::fromArray([[10, 20], [0, 10]]), 10.0],
            'MultiPoint, one of two is empty' =>
                [MultiPoint::fromArray([[], [0, 10]]), 10.0],
            'GeometryCollection, closest component is 10' =>
                [new GeometryCollection([new Point(0, 10), new Point()]), 10.0]
            // FIXME: this geometry collection crashes GEOS
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
        // GEOS dosn't support EMPTY Points
        $geosAvailable = \GeoPHP\GeoPHP::geosInstalled();
        \GeoPHP\GeoPHP::geosInstalled(false);
        $point = new Point(0, 0);
        
        parent::assertSame($point->distance($otherGeometry), $expectedDistance);
        \GeoPHP\GeoPHP::geosInstalled($geosAvailable);
    }

    /**
     * @dataProvider providerDistance
     *
     * @param Geometry $otherGeometry
     * @return void
     */
    public function testDistanceEmpty($otherGeometry)
    {
        $point = new Point();
        parent::assertNull($point->distance($otherGeometry));
    }

    /**
     * @return void
     */
    public function testTrivialMethods()
    {
        $point = new Point(1, 2, 3, 4);

        parent::assertSame($point->dimension(), 0);

        parent::assertSame($point->numPoints(), 1);

        parent::assertSame($point->numGeometries(), 1);
        
        parent::assertSame($point->getPoints(), [$point]);

        parent::assertTrue($point->isSimple());
        
        parent::assertTrue($point->isClosed());
        
        parent::assertSame($point->explode(), []);
    }

    /**
     * @return void
     */
    public function testMinMaxMethods()
    {
        $point = new Point(1, 2, 3, 4);

        parent::assertEquals($point->minimumZ(), 3);
        parent::assertEquals($point->maximumZ(), 3);
        parent::assertEquals($point->minimumM(), 4);
        parent::assertEquals($point->maximumM(), 4);
    }

    /**
     * @return array[]
     */
    public function providerMethodsNotValidForPointReturnsNull()
    {
        return [
            ['zDifference'],
            ['elevationGain'],
            ['elevationLoss'],
            //['numGeometries'], # returns 1
            //['geometryN'], # raises TypeError
            ['startPoint'],
            ['endPoint'],
            //['isRing'], # throws UnsupportedMethodException
            //['isClosed'], # returns true
            //['pointN'], # raises TypeError
            ['exteriorRing'],
            ['numInteriorRings'],
            //['interiorRingN'], # raises TypeError
            //['explode'] # returns array
        ];
    }

    /**
     * @dataProvider providerMethodsNotValidForPointReturnsNull
     *
     * @param string $methodName
     * @return void
     */
    public function testPlaceholderMethodsReturnsNull($methodName)
    {
        parent::assertNull((new Point(1, 2, 3, 4))->$methodName(null));
    }

    /**
     * @return array[]
     */
    public function providerMethodsNotValidForPointReturns0()
    {
        return [
            ['getArea'],
            ['getLength'],
            ['length3D'],
            ['greatCircleLength'],
            ['haversineLength']
        ];
    }

    /**
     * @dataProvider providerMethodsNotValidForPointReturns0
     *
     * @param string $methodName
     * @return void
     */
    public function testPlaceholderMethods($methodName)
    {
        parent::assertSame((new Point(1, 2, 3, 4))->$methodName(null), 0.0);
    }
}
