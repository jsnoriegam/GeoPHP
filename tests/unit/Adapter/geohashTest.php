<?php

namespace GeoPHP\Tests\Adapter;

use \GeoPHP\Adapter\GeoHash;
use PHPUnit\Framework\TestCase;

class GeoHashTest extends TestCase
{

    /**
     * test cases for adjacent geohashes.
     * @return void
     */
    public function testAdjacent()
    {
        $this->assertEquals('xne', GeoHash::adjacent('xn7', 'top'), 'Did not find correct top adjacent geohash for xn7');
        $this->assertEquals('xnk', GeoHash::adjacent('xn7', 'right'), 'Did not find correct right adjacent geohash for xn7');
        $this->assertEquals('xn5', GeoHash::adjacent('xn7', 'bottom'), 'Did not find correct bottom adjacent geohash for xn7');
        $this->assertEquals('xn6', GeoHash::adjacent('xn7', 'left'), 'Did not find correct left adjacent geohash for xn7');
        $this->assertEquals('xnd', GeoHash::adjacent(GeoHash::adjacent('xn7', 'left'), 'top'), 'Did not find correct top-left adjacent geohash for xn7');
    }
}
