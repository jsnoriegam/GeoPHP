<?php

namespace GeoPHP\Tests\Geometry;

use \GeoPHP\Exception\InvalidGeometryException;
use \GeoPHP\Geometry\Point;
use \GeoPHP\Geometry\MultiPoint;
use \PHPUnit\Framework\TestCase;

/**
 * Unit tests of MultiPoint geometry
 *
 * @group geometry
 *
 */
class MultiPointTest extends TestCase
{

    /**
     * @return array[]
     */
    public function providerValidComponents()
    {
        return [
            [[]],                                   // no components, empty MultiPoint
            [[new Point()]],                        // empty component
            [[new Point(1, 2)]],
            [[new Point(1, 2), new Point(3, 4)]],
            [[new Point(1, 2, 3, 4), new Point(5, 6, 7, 8)]],
        ];
    }

    /**
     * @dataProvider providerValidComponents
     *
     * @param Point[] $points
     * @return void
     */
    public function testValidComponents($points)
    {
        parent::assertNotNull(new MultiPoint($points));
    }

    /**
     * @return array[]
     */
    public function providerInvalidComponents()
    {
        return [
            [[\GeoPHP\Geometry\LineString::fromArray([[1,2],[3,4]])]],  // wrong component type
        ];
    }

    /**
     * @dataProvider providerInvalidComponents
     *
     * @param Point[] $components
     * @return void
     */
    public function testConstructorWithInvalidComponents($components)
    {
        $this->expectException(InvalidGeometryException::class);
        new MultiPoint($components);
    }

    /**
     * @return void
     */
    public function testGeometryType()
    {
        $multiPoint = new MultiPoint();

        parent::assertEquals(\GeoPHP\Geometry\Geometry::MULTI_POINT, $multiPoint->geometryType());

        parent::assertInstanceOf('\GeoPHP\Geometry\MultiPoint', $multiPoint);
        parent::assertInstanceOf('\GeoPHP\Geometry\MultiGeometry', $multiPoint);
        parent::assertInstanceOf('\GeoPHP\Geometry\Geometry', $multiPoint);
    }

    /**
     * @return void
     */
    public function testIs3D()
    {
        parent::assertTrue((new Point(1, 2, 3))->is3D());
        parent::assertTrue((new Point(1, 2, 3, 4))->is3D());
        parent::assertFalse((new Point(null, null, 3, 4))->is3D());
    }

    /**
     * @return void
     */
    public function testIsMeasured()
    {
        parent::assertTrue((new Point(1, 2, null, 4))->isMeasured());
        parent::assertFalse((new Point(null, null, null, 4))->isMeasured());
    }

    /**
     * @return array[]
     */
    public function providerCentroid()
    {
        return [
            [[], []],
            [[[0, 0], [0, 10]], [0, 5]]
        ];
    }

    /**
     * @dataProvider providerCentroid
     *
     * @param array[] $components
     * @param array<mixed> $coords
     * @return void
     */
    public function testCentroid($components, $coords)
    {
        $pointA = MultiPoint::fromArray($components)->getCentroid();
        $pointB = Point::fromArray($coords);

        $pointA->setGeos();
        $pointB->setGeos();
        
        parent::assertEquals($pointA, $pointB);
    }

    /**
     * @return array[]
     */
    public function providerIsSimple()
    {
        return [
            [[], true],
            [[[0, 0], [0, 10]], true],
            [[[1, 1], [2, 2], [1, 3], [1, 2], [2, 1]], true],
            [[[0, 10], [0, 10]], false],
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
        $multiPoint = MultiPoint::fromArray($points);
        parent::assertSame($multiPoint->isSimple(), $result);
    }

    /**
     * @dataProvider providerValidComponents
     *
     * @param Point[] $points
     * @return void
     */
    public function testNumPoints($points)
    {
        $multiPoint = new MultiPoint($points);
        parent::assertEquals($multiPoint->numPoints(), $multiPoint->numGeometries());
    }

    /**
     * @return void
     */
    public function testTrivialAndNotValidMethods()
    {
        $point = new MultiPoint();

        parent::assertSame($point->dimension(), 0);

        parent::assertEquals($point->boundary(), new \GeoPHP\Geometry\GeometryCollection());

        if (method_exists($this, "assertIsArray")) {
            parent::assertIsArray($point->explode());
        }

        parent::assertTrue($point->isSimple());
    }
}
