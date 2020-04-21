<?php
/**
 * This file contains the BinaryReader class.
 * For more information see the class description below.
 *
 * @author Peter Bathory <peter.bathory@cartographia.hu>
 * @since 2016-02-18
 *
 * This code is open-source and licenced under the Modified BSD License.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace geoPHP\Adapter;

/**
 * Helper class BinaryWriter
 *
 * A simple binary writer supporting both byte orders
 */
class BinaryWriter extends BinaryAdapter
{
    
    public function __construct($endianness = 0)
    {
        $this->setEndianness($endianness);
    }

    /**
     * Writes a signed 8-bit integer
     *
     * @param int $value
     * @return string The integer as a binary string
     */
    public function writeSInt8($value): string
    {
        return pack('c', $value);
    }

    /**
     * Writes an unsigned 8-bit integer
     *
     * @param int $value
     * @return string The integer as a binary string
     */
    public function writeUInt8($value): string
    {
        return pack('C', $value);
    }

    /**
     * Writes an unsigned 32-bit integer
     *
     * @param int $value
     * @return string The integer as a binary string
     */
    public function writeUInt32($value): string
    {
        return pack($this->isLittleEndian() ? 'V' : 'N', $value);
    }

    /**
     * Writes a double
     *
     * @param float $value
     * @return string The floating point number as a binary string
     */
    public function writeDouble($value): string
    {
        return $this->isLittleEndian() ? pack('d', $value) : strrev(pack('d', $value));
    }

    /**
     * Writes a positive integer as an unsigned base-128 varint
     * Ported from https://github.com/cschwarz/wkx/blob/master/lib/binaryreader.js
     *
     * @param int $value
     * @return string The integer as a binary string
     */
    public function writeUVarInt(int $value): string
    {
        $out = '';

        while (($value & 0xFFFFFF80) !== 0) {
            $out .= $this->writeUInt8(($value & 0x7F) | 0x80);
            // Zero fill by 7 zero
            if ($value >= 0) {
                $value >>= 7;
            } else {
                $value = ((~$value) >> 7) ^ (0x7fffffff >> (7 - 1));
            }
        }

        $out .= $this->writeUInt8($value & 0x7F);

        return $out;
    }

    /**
     * Writes an integer as a signed base-128 varint
     *
     * @param int $value
     * @return string The integer as a binary string
     */
    public function writeSVarInt(int $value): string
    {
        return $this->writeUVarInt(self::zigZagEncode($value));
    }

    /**
     * ZigZag encoding maps signed integers to unsigned integers
     *
     * @param int $value Signed integer
     * @return int Encoded positive integer value
     */
    public static function zigZagEncode(int $value): int
    {
        return ($value << 1) ^ ($value >> 31);
    }
}
