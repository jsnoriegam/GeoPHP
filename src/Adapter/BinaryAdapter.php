<?php

/**
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace geoPHP\Adapter;

/**
 * Base class for BinaryReader and BinaryWriter
 */
abstract class BinaryAdapter
{

    const BIG_ENDIAN = 0;
    const LITTLE_ENDIAN = 1;

    /**
     * @var int 0 if reader is in BigEndian mode or 1 if in LittleEndian mode
     */
    private $endianness = 0;

    /**
     * @param int $endian self::BIG_ENDIAN or self::LITTLE_ENDIAN
     */
    public function setEndianness(int $endian)
    {
        $this->endianness = $endian === self::BIG_ENDIAN ? self::BIG_ENDIAN : self::LITTLE_ENDIAN;
    }

    /**
     * @return int Returns 0 if reader is in BigEndian mode or 1 if in LittleEndian mode
     */
    public function getEndianness(): int
    {
        return $this->endianness;
    }

    /**
     * @return bool Returns true if Writer is in BigEndian mode
     */
    public function isBigEndian(): bool
    {
        return $this->endianness === self::BIG_ENDIAN;
    }

    /**
     * @return bool Returns true if Writer is in LittleEndian mode
     */
    public function isLittleEndian(): bool
    {
        return $this->endianness === self::LITTLE_ENDIAN;
    }

}
