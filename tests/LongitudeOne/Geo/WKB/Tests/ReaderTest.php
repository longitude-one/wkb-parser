<?php

/**
 * This file is part of the LongitudeOne WKB-Parser project.
 *
 * PHP 8.1 | 8.2 | 8.3
 *
 * Copyright LongitudeOne - Alexandre Tranchant - Derek J. Lambert.
 * Copyright 2024.
 *
 */

namespace LongitudeOne\Geo\WKB\Tests;

use LongitudeOne\Geo\WKB\Exception\InvalidArgumentException;
use LongitudeOne\Geo\WKB\Exception\RangeException;
use LongitudeOne\Geo\WKB\Reader;
use PHPUnit\Framework\TestCase;

/**
 * Reader tests.
 *
 * @covers \LongitudeOne\Geo\WKB\Reader
 */
class ReaderTest extends TestCase
{
    /**
     * @return array[]
     */
    public static function badTestData(): array
    {
        return [
            'readNullPackage' => [
                'value' => null,
                'methods' => ['readByteOrder'],
                'exception' => InvalidArgumentException::class,
                'message' => 'LongitudeOne\Geo\WKB\Reader: Error number 2: unpack(): Type C: not enough input values, need 1 values but only 0 were provided',
            ],
            'readBinaryBadByteOrder' => [
                'value' => pack('H*', '04'),
                'methods' => ['readByteOrder'],
                'exception' => '\LongitudeOne\Geo\WKB\Exception\UnexpectedValueException',
                'message' => 'Invalid byte order "4"',
            ],
            'readBinaryWithoutByteOrder' => [
                'value' => pack('H*', '0101000000'),
                'methods' => ['readLong'],
                'exception' => '\LongitudeOne\Geo\WKB\Exception\UnexpectedValueException',
                'message' => 'Invalid byte order "unset"',
            ],
            'readHexWithoutByteOrder' => [
                'value' => '0101000000',
                'methods' => ['readLong'],
                'exception' => '\LongitudeOne\Geo\WKB\Exception\UnexpectedValueException',
                'message' => 'Invalid byte order "unset"',
            ],
            'readBinaryShortFloat' => [
                'value' => pack('H*', '013D0AD'),
                'methods' => ['readByteOrder', 'readFloat'],
                'exception' => InvalidArgumentException::class,
                'message' => '/Type d: not enough input values, need 8 values but only 3 were provided$/',
            ],
        ];
    }

    /**
     * @return array[]
     */
    public static function goodTestData(): array
    {
        return [
            'readBinaryByteOrder' => [
                'value' => pack('H*', '01'),
                'methods' => [
                    ['readByteOrder', null, 1],
                ],
            ],
            'readHexByteOrder' => [
                'value' => '01',
                'methods' => [
                    ['readByteOrder', null, 1],
                ],
            ],
            'readPrefixedHexByteOrder' => [
                'value' => '0x01',
                'methods' => [
                    ['readByteOrder', null, 1],
                ],
            ],
            'readNDRBinaryLong' => [
                'value' => pack('H*', '0101000000'),
                'methods' => [
                    ['readByteOrder', null, 1],
                    ['readLong', null, 1],
                ],
            ],
            'readXDRBinaryLong' => [
                'value' => pack('H*', '0000000001'),
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['readLong', null, 1],
                ],
            ],
            'readNDRHexLong' => [
                'value' => '0101000000',
                'methods' => [
                    ['readByteOrder', null, 1],
                    ['readLong', null, 1],
                ],
            ],
            'readXDRHexLong' => [
                'value' => '0000000001',
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['readLong', null, 1],
                ],
            ],
            'readNDRBinaryFloat' => [
                'value' => pack('H*', '013D0AD7A3701D4140'),
                'methods' => [
                    ['readByteOrder', null, 1],
                    ['readFloat', null, 34.23],
                ],
            ],
            'readNDRBinaryDouble' => [
                'value' => pack('H*', '013D0AD7A3701D4140'),
                'methods' => [
                    ['readByteOrder', null, 1],
                    ['readDouble', null, 34.23],
                ],
            ],
            'readXDRBinaryFloat' => [
                'value' => pack('H*', '0040411D70A3D70A3D'),
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['readFloat', null, 34.23],
                ],
            ],
            'readNDRHexFloat' => [
                'value' => '013D0AD7A3701D4140',
                'methods' => [
                    ['readByteOrder', null, 1],
                    ['readFloat', null, 34.23],
                ],
            ],
            'readXDRHexFloat' => [
                'value' => '0040411D70A3D70A3D',
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['readFloat', null, 34.23],
                ],
            ],
            'readXDRBinaryFloats' => [
                'value' => pack('H*', '0040411D70A3D70A3D40411D70A3D70A3D'),
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['readFloats', 2, [34.23, 34.23]],
                ],
            ],
            'readXDRBinaryDoubles' => [
                'value' => pack('H*', '0040411D70A3D70A3D40411D70A3D70A3D'),
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['readDoubles', 2, [34.23, 34.23]],
                ],
            ],
            'readXDRPosition' => [
                'value' => pack('H*', '0040411D70A3D70A3D40411D70A3D70A3D'),
                'methods' => [
                    ['readByteOrder', null, 0],
                    ['getCurrentPosition', null, 1],
                    ['getLastPosition', null, 0],
                    ['readFloat', null, 34.23],
                    ['getCurrentPosition', null, 9],
                    ['getLastPosition', null, 1],
                    ['readFloat', null, 34.23],
                    ['getCurrentPosition', null, 17],
                    ['getLastPosition', null, 9],
                ],
            ],
        ];
    }

    /**
     * @param string $exception
     * @param string $message
     *
     * @dataProvider badTestData
     */
    public function testBad($value, array $methods, $exception, $message)
    {
        self::expectException($exception);

        if ('/' === $message[0]) {
            self::expectExceptionMessageMatches($message);
        } else {
            $this->expectExceptionMessage($message);
        }

        $reader = new Reader($value);

        foreach ($methods as $method) {
            $reader->$method();
        }
    }

    /**
     * @dataProvider goodTestData
     */
    public function testGood($value, array $methods)
    {
        $reader = new Reader($value);

        foreach ($methods as $test) {
            list($method, $argument, $expected) = $test;

            $actual = $reader->$method($argument);

            $this->assertSame($expected, $actual);
        }
    }

    public function testReaderReuse()
    {
        $reader = new Reader();

        $value = '01';
        $value = pack('H*', $value);

        $reader->load($value);

        $result = $reader->readByteOrder();

        $this->assertEquals(1, $result);

        $value = '01';

        $reader->load($value);

        $result = $reader->readByteOrder();

        $this->assertEquals(1, $result);

        $value = '0x01';

        $reader->load($value);

        $result = $reader->readByteOrder();

        $this->assertEquals(1, $result);

        $value = '0040411D70A3D70A3D';
        $value = pack('H*', $value);

        $reader->load($value);

        $reader->readByteOrder();

        $result = $reader->readFloat();

        $this->assertEquals(34.23, $result);
    }
}
