<?php

namespace geoPHP\Tests\Geometry;

use \geoPHP\Exception\InvalidGeometryException;
use \geoPHP\Geometry\Point;
use \geoPHP\Geometry\MultiPoint;
use \PHPUnit\Framework\TestCase;

/**
 * Unit tests of MultiPoint geometry
 *
 * @group geometry
 *
 */
class MultiPointTest extends TestCase
{

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
     */
    public function testValidComponents($points)
    {
        parent::assertNotNull(new MultiPoint($points));
    }

    public function providerInvalidComponents()
    {
        return [
            [[\geoPHP\Geometry\LineString::fromArray([[1,2],[3,4]])]],  // wrong component type
        ];
    }

    /**
     * @dataProvider providerInvalidComponents
     *
     * @param mixed $components
     */
    public function testConstructorWithInvalidComponents($components)
    {
        $this->expectException(InvalidGeometryException::class);

        new MultiPoint($components);
    }

    public function testGeometryType()
    {
        $multiPoint = new MultiPoint();

        parent::assertEquals(\geoPHP\Geometry\Geometry::MULTI_POINT, $multiPoint->geometryType());

        parent::assertInstanceOf('\geoPHP\Geometry\MultiPoint', $multiPoint);
        parent::assertInstanceOf('\geoPHP\Geometry\MultiGeometry', $multiPoint);
        parent::assertInstanceOf('\geoPHP\Geometry\Geometry', $multiPoint);
    }

    public function testIs3D()
    {
        parent::assertTrue( (new Point(1, 2, 3))->is3D() );
        parent::assertTrue( (new Point(1, 2, 3, 4))->is3D() );
        parent::assertFalse( (new Point(null, null, 3, 4))->is3D() );
    }

    public function testIsMeasured()
    {
        parent::assertTrue( (new Point(1, 2, null, 4))->isMeasured() );
        parent::assertFalse( (new Point(null, null , null, 4))->isMeasured() );
    }

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
     * @param array $components
     * @param array $coords
     */
    public function testCentroid($components, $coords)
    {
        $pointA = MultiPoint::fromArray($components)->getCentroid();
        $pointB = Point::fromArray($coords);

        $pointA->setGeos();
        $pointB->setGeos();
        
        parent::assertEquals($pointA, $pointB);
    }

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
     * @param array $points
     * @param bool  $result
     */
    public function testIsSimple($points, $result)
    {
        $multiPoint = MultiPoint::fromArray($points);

        parent::assertSame($multiPoint->isSimple(), $result);
    }

    /**
     * @dataProvider providerValidComponents
     *
     * @param array $points
     */
    public function testNumPoints($points)
    {
        $multiPoint = new MultiPoint($points);

        parent::assertEquals($multiPoint->numPoints(), $multiPoint->numGeometries());
    }

    public function testTrivialAndNotValidMethods()
    {
        $point = new MultiPoint();

        parent::assertSame( $point->dimension(), 0 );

        parent::assertEquals( $point->boundary(), new \geoPHP\Geometry\GeometryCollection() );

        if (method_exists($this, "assertIsArray")) {
            parent::assertIsArray( $point->explode());
        }

        parent::assertTrue( $point->isSimple());
    }

}
