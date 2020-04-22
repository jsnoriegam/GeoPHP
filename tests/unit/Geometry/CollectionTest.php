<?php
/**
 * This file contains the CollectionTest class.
 * For more information see the class description below.
 *
 * @author Peter Bathory <peter.bathory@cartographia.hu>
 * @since 2020-03-19
 */

namespace geoPHP\Tests\Geometry;

use \geoPHP\Geometry\Collection;
use \geoPHP\Geometry\Point;
use \geoPHP\Geometry\LineString;
use \PHPUnit\Framework\TestCase;

/**
 * This class... TODO: Complete this
 */
class CollectionTest extends TestCase
{

    public function providerIs3D()
    {
        return [
                [[new Point(1, 2)], false],
                [[new Point(1, 2, 3)], true],
                [[new Point(1, 2, 3), new Point(1, 2)], true],
        ];
    }

    /**
     * @dataProvider providerIs3D
     *
     * @param Point[] $components
     * @param bool    $result
     */
    public function testIs3D($components, $result)
    {
        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [$components, true]);

        parent::assertEquals($stub->is3D(), $result);
    }

    public function providerIsMeasured()
    {
        return [
                [[new Point()], false],
                [[new Point(1, 2)], false],
                [[new Point(1, 2, 3)], false],
                [[new Point(1, 2, 3, 4)], true],
                [[new Point(1, 2, 3, 4), new Point(1, 2)], true],
        ];
    }

    /**
     * @dataProvider providerIsMeasured
     *
     * @param Point[] $components
     * @param bool    $result
     */
    public function testIsMeasured($components, $result)
    {
        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [$components, true]);

        parent::assertEquals($stub->isMeasured(), $result);
    }

    public function providerIsEmpty()
    {
        return [
                [[], true],
                [[new Point()], true],
                [[new Point(1, 2)], false],
        ];
    }

    /**
     * @dataProvider providerIsEmpty
     *
     * @param Point[] $components
     * @param bool    $result
     */
    public function testIsEmpty($components, $result)
    {
        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [$components, true]);

        parent::assertEquals($stub->isEmpty(), $result);
    }

    public function testNonApplicableMethods()
    {
        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [[], true]);

        parent::assertNull($stub->x());
        parent::assertNull($stub->y());
        parent::assertNull($stub->z());
        parent::assertNull($stub->m());
    }

    public function testAsArray()
    {
        $components = [
                new Point(1, 2),
                new LineString()
        ];
        $expected = [
                [1, 2],
                []
        ];

        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [$components, true]);

        parent::assertEquals($stub->asArray(), $expected);
    }

    public function testFlatten()
    {
        $components = [
                new Point(1, 2, 3, 4),
                new Point(5, 6, 7, 8),
                new LineString([new Point(1, 2, 3, 4), new Point(5, 6, 7, 8)]),
        ];

        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [$components]);
        $stub->flatten();

        parent::assertFalse($stub->hasZ());
        parent::assertFalse($stub->isMeasured());
        parent::assertFalse($stub->getPoints()[0]->hasZ());
    }

    public function testExplode()
    {
        $points = [new Point(1, 2), new Point(3, 4), new Point(5, 6), new Point(1, 2)];
        $components = [
                new \geoPHP\Geometry\Polygon([new LineString($points)])
        ];

        /** @var Collection $stub */
        $stub = $this->getMockForAbstractClass(Collection::class, [$components]);

        $segments = $stub->explode();
        parent::assertCount(count($points) - 1, $segments);
        foreach ($segments as $i => $segment) {
            parent::assertCount(2, $segment->getComponents());

            parent::assertSame($segment->startPoint(), $points[$i]);
            parent::assertSame($segment->endPoint(), $points[$i + 1]);
        }
    }
}