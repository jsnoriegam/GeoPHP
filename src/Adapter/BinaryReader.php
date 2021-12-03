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
 * Helper class BinaryReader
 *
 * A simple binary reader supporting both byte orders
 */
class BinaryReader extends BinaryAdapter
{

    /**
     * @var resource file pointer
     */
    private $buffer;
    
    /**
     * Opens a memory buffer with the given input
     *
     * @param string $input
     */
    public function __construct(string $input)
    {
        $this->open($input);
    }

    /**
     * Opens the memory buffer
     * 
     * @param string $input
     * @throws \Exception
     * @return void
     */
    public function open(string $input)
    {
        $stream = fopen('php://memory', 'x+');
        if ($stream === false) {
            throw new \Exception("Error. Could not open PHP memory for writing.");
        }
        $this->buffer = $stream;
        fwrite($this->buffer, $input);
        fseek($this->buffer, 0);
    }
    
    /**
     * Closes the memory buffer
     * @return void
     */
    public function close()
    {
        if (isset($this->buffer) && is_resource($this->buffer)) {
            fclose($this->buffer);
        }
        unset($this->buffer);
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }
    
    /**
     * Reads a signed 8-bit integer from the buffer
     *
     * @return int|null
     */
    public function readSInt8()
    {
        $char = fread($this->buffer, 1);
        $data = !empty($char) ? unpack("c", $char) : [];
        return !empty($data) ? current($data) : null;
    }

    /**
     * Reads an unsigned 8-bit integer from the buffer
     *
     * @return int|null
     */
    public function readUInt8()
    {
        $char = fread($this->buffer, 1);
        $data = !empty($char) ? unpack("C", $char) : [];
        return !empty($data) ? current($data) : null;
    }

    /**
     * Reads an unsigned 32-bit integer from the buffer
     *
     * @return int|null
     */
    public function readUInt32()
    {
        $int32 = fread($this->buffer, 4);
        $data = !empty($int32) ? unpack($this->isLittleEndian() ? 'V' : 'N', $int32) : [];
        return !empty($data) ? current($data) : null;
    }

    /**
     * Reads one or more double values from the buffer
     * @param int $length How many double values to read. Default is 1
     *
     * @return float[]
     */
    public function readDoubles($length = 1): array
    {
        $bin = fread($this->buffer, $length);
        $data = !empty($bin) ? ($this->isLittleEndian() ? unpack("d*", $bin) : unpack("d*", strrev($bin))) : [];
        return is_array($data) ? array_values($data) : [];
    }

    /**
     * Reads an unsigned base-128 varint from the buffer
     * Ported from https://github.com/cschwarz/wkx/blob/master/lib/binaryreader.js
     *
     * @return int
     */
    public function readUVarInt(): int
    {
        $result = 0;
        $bytesRead = 0;

        do {
            $nextByte = $this->readUInt8();
            $result += ($nextByte & 0x7F) << (7 * $bytesRead);
            ++$bytesRead;
        } while ($nextByte >= 0x80);
        return $result;
    }

    /**
     * Reads a signed base-128 varint from the buffer
     *
     * @return int
     */
    public function readSVarInt(): int
    {
        return self::zigZagDecode($this->readUVarInt());
    }

    /**
     * ZigZag decoding maps unsigned integers to signed integers
     *
     * @param int $value Encrypted positive integer value
     * @return int Decoded signed integer
     */
    public static function zigZagDecode($value): int
    {
        return ($value & 1) === 0 ? $value >> 1 : -($value >> 1) - 1;
    }
}
