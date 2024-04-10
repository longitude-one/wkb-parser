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

use LongitudeOne\Geo\WKB\Exception\ExceptionInterface;
use LongitudeOne\Geo\WKB\Exception\InvalidArgumentException;
use LongitudeOne\Geo\WKB\Exception\UnexpectedValueException;
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
     * @return \Generator<string, array{value:string|null, methods:string[], exception:class-string<ExceptionInterface>, message:string}, null, void>
     */
    public static function badTestData(): \Generator
    {
        yield 'readNullPackage' => [
            'value' => null,
            'methods' => ['readByteOrder'],
            'exception' => InvalidArgumentException::class,
            'message' => 'LongitudeOne\Geo\WKB\Reader: Error number 1: No input data to read. Input is null.',
        ];
        // read empty package
        $message = 'LongitudeOne\Geo\WKB\Reader: Error number 2: unpack(): Type C: not enough input values, need 1 values but only 0 were provided.';
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $message = 'LongitudeOne\Geo\WKB\Reader: Error number 2: unpack(): Type C: not enough input, need 1, have 0.';
        }
        yield 'readEmptyPackage' => [
            'value' => '',
            'methods' => ['readByteOrder'],
            'exception' => InvalidArgumentException::class,
            'message' => $message,
        ];
        yield 'readBinaryBadByteOrder' => [
            'value' => pack('H*', '04'),
            'methods' => ['readByteOrder'],
            'exception' => UnexpectedValueException::class,
            'message' => 'Invalid byte order "4"',
        ];
        yield 'readBinaryWithoutByteOrder' => [
            'value' => pack('H*', '0101000000'),
            'methods' => ['readLong'],
            'exception' => UnexpectedValueException::class,
            'message' => 'Invalid byte order "unset"',
        ];
        yield 'readHexWithoutByteOrder' => [
            'value' => '0101000000',
            'methods' => ['readLong'],
            'exception' => UnexpectedValueException::class,
            'message' => 'Invalid byte order "unset"',
        ];

        // Read Binary Short Float
        $message = '/Type d: not enough input values, need 8 values but only 3 were provided\.$/';
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $message = 'LongitudeOne\Geo\WKB\Reader: Error number 2: unpack(): Type d: not enough input, need 8, have 3.';
        }
        yield 'readBinaryShortFloat' => [
            'value' => pack('H*', '013D0AD'),
            'methods' => ['readByteOrder', 'readFloat'],
            'exception' => InvalidArgumentException::class,
            'message' => $message,
        ];
        $message = '/Type d: not enough input values, need 8 values but only 3 were provided\.$/';
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $message = 'LongitudeOne\Geo\WKB\Reader: Error number 2: unpack(): Type d: not enough input, need 8, have 3.';
        }
        yield 'readHexShortFloat' => [
            'value' => '013D0AD',
            'methods' => ['readByteOrder', 'readFloat'],
            'exception' => InvalidArgumentException::class,
            'message' => $message,
        ];
    }

    /**
     * @return \Generator<string, array{value:string, methods:array{0:string, 1:int|null, 2:float|float[]|int|null}[]}, null, void>
     */
    public static function goodTestData(): \Generator
    {
        yield 'readBinaryByteOrder' => [
            'value' => pack('H*', '01'),
            'methods' => [
                ['readByteOrder', null, 1],
            ],
        ];
        yield 'readHexByteOrder' => [
            'value' => '01',
            'methods' => [
                ['readByteOrder', null, 1],
            ],
        ];
        yield 'readPrefixedHexByteOrder' => [
            'value' => '0x01',
            'methods' => [
                ['readByteOrder', null, 1],
            ],
        ];
        yield 'readNDRBinaryLong' => [
            'value' => pack('H*', '0101000000'),
            'methods' => [
                ['readByteOrder', null, 1],
                ['readLong', null, 1],
            ],
        ];
        yield 'readXDRBinaryLong' => [
            'value' => pack('H*', '0000000001'),
            'methods' => [
                ['readByteOrder', null, 0],
                ['readLong', null, 1],
            ],
        ];
        yield 'readNDRHexLong' => [
            'value' => '0101000000',
            'methods' => [
                ['readByteOrder', null, 1],
                ['readLong', null, 1],
            ],
        ];
        yield 'readXDRHexLong' => [
            'value' => '0000000001',
            'methods' => [
                ['readByteOrder', null, 0],
                ['readLong', null, 1],
            ],
        ];
        yield 'readNDRBinaryFloat' => [
            'value' => pack('H*', '013D0AD7A3701D4140'),
            'methods' => [
                ['readByteOrder', null, 1],
                ['readFloat', null, 34.23],
            ],
        ];
        yield 'readXDRBinaryFloat' => [
            'value' => pack('H*', '0040411D70A3D70A3D'),
            'methods' => [
                ['readByteOrder', null, 0],
                ['readFloat', null, 34.23],
            ],
        ];
        yield 'readNDRHexFloat' => [
            'value' => '013D0AD7A3701D4140',
            'methods' => [
                ['readByteOrder', null, 1],
                ['readFloat', null, 34.23],
            ],
        ];
        yield 'readXDRHexFloat' => [
            'value' => '0040411D70A3D70A3D',
            'methods' => [
                ['readByteOrder', null, 0],
                ['readFloat', null, 34.23],
            ],
        ];
        yield 'readXDRBinaryFloats' => [
            'value' => pack('H*', '0040411D70A3D70A3D40411D70A3D70A3D'),
            'methods' => [
                ['readByteOrder', null, 0],
                ['readFloats', 2, [34.23, 34.23]],
            ],
        ];
        yield 'readXDRPosition' => [
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
        ];
    }

    /**
     * @param string[]                         $methods
     * @param class-string<ExceptionInterface> $exception
     *
     * @dataProvider badTestData
     */
    public function testBad(?string $value, array $methods, string $exception, string $message): void
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
     * @param array{0:string, 1:float|int|null, 2:array<int|float>|int|float|null}[] $methods
     *
     * @dataProvider goodTestData
     */
    public function testGood(string $value, array $methods): void
    {
        $reader = new Reader($value);

        foreach ($methods as $test) {
            list($method, $argument, $expected) = $test;

            $actual = $reader->$method($argument);

            $this->assertSame($expected, $actual);
        }
    }

    public function testReaderReuse(): void
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
