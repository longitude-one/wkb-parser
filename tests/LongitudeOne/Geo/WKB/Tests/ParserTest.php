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
use LongitudeOne\Geo\WKB\Exception\UnexpectedValueException;
use LongitudeOne\Geo\WKB\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parser tests.
 *
 * @covers \LongitudeOne\Geo\WKB\Parser
 */
class ParserTest extends TestCase
{
    /**
     * @return array[]
     */
    public static function badBinaryData(): array
    {
        return [
            'badByteOrder' => [
                'value' => pack('H*', '03010000003D0AD7A3701D41400000000000C055C0'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Invalid byte order "3" at byte 0',
            ],
            'badSimpleType' => [
                'value' => pack('H*', '01150000003D0AD7A3701D41400000000000C055C0'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Unsupported WKB type "21" (0x15) at byte 1',
            ],
            'shortNDRPoint' => [
                'value' => pack('H*', '01010000003D0AD7A3701D414000000000'),
                'exception' => InvalidArgumentException::class,
                'message' => 'Type d: not enough input values, need 8 values but only 4 were provided',
            ],
            'badPointSize' => [
                'value' => pack('H*', '0000000FA1'),
                'exception' => UnexpectedValueException::class,
                'message' => 'POINT with unsupported dimensions 0xFA0 (4000) at byte 1',
            ],
            'badPointInMultiPoint' => [
                'value' => pack('H*', '0080000004000000020000000001'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Bad POINT with dimensions 0x0 (0) in MULTIPOINT, expected dimensions 0x80000000 (2147483648) at byte 10',
            ],
            'unexpectedLineStringInMultiPoint' => [
                'value' => pack('H*', '0080000004000000020000000002'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Unexpected LINESTRING with dimensions 0x0 (0) in MULTIPOINT, expected POINT with dimensions 0x80000000 (2147483648) at byte 10',
            ],
            'badLineStringInMultiLineString' => [
                'value' => pack('H*', '0000000005000000020080000002'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Bad LINESTRING with dimensions 0x80000000 (2147483648) in MULTILINESTRING, expected dimensions 0x0 (0) at byte 10',
            ],
            'badPolygonInMultiPolygon' => [
                'value' => pack('H*', '0080000006000000020000000003'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Bad POLYGON with dimensions 0x0 (0) in MULTIPOLYGON, expected dimensions 0x80000000 (2147483648) at byte 10',
            ],
            'badCircularStringInCompoundCurve' => [
                'value' => pack('H*', '0080000009000000020000000008'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Bad CIRCULARSTRING with dimensions 0x0 (0) in COMPOUNDCURVE, expected dimensions 0x80000000 (2147483648) at byte 10',
            ],
            'unexpectedPointInCompoundCurve' => [
                'value' => pack('H*', '0080000009000000020000000001'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Unexpected POINT with dimensions 0x0 (0) in COMPOUNDCURVE, expected LINESTRING or CIRCULARSTRING with dimensions 0x80000000 (2147483648) at byte 10',
            ],
            'badCompoundCurveInCurvePolygon' => [
                'value' => pack('H*', '000000000a000000010080000009'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Bad COMPOUNDCURVE with dimensions 0x80000000 (2147483648) in CURVEPOLYGON, expected dimensions 0x0 (0) at byte 10',
            ],
            'badCircularStringInCurvePolygon' => [
                'value' => pack('H*', '008000000a000000010080000009000000020000000008'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Bad CIRCULARSTRING with dimensions 0x0 (0) in CURVEPOLYGON, expected dimensions 0x80000000 (2147483648) at byte 19',
            ],
            'unexpectedPolygonInMultiCurve' => [
                'value' => pack('H*', '004000000b000000010040000003'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Unexpected POLYGON with dimensions 0x40000000 (1073741824) in MULTICURVE, expected LINESTRING, CIRCULARSTRING or COMPOUNDCURVE with dimensions 0x40000000 (1073741824) at byte 10',
            ],
            'unexpectedPointInMultiSurface' => [
                'value' => pack('H*', '008000000c000000020080000001'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Unexpected POINT with dimensions 0x80000000 (2147483648) in MULTISURFACE, expected POLYGON or CURVEPOLYGON with dimensions 0x80000000 (2147483648) at byte 10',
            ],
            'unexpectedPointInPolyhedralSurface' => [
                'value' => pack('H*', '010f000080050000000101000080'),
                'exception' => UnexpectedValueException::class,
                'message' => 'Unexpected POINT with dimensions 0x80000000 (2147483648) in POLYHEDRALSURFACE, expected POLYGON with dimensions 0x80000000 (2147483648) at byte 10',
            ],
        ];
    }

    public static function goodBinaryData(): array
    {
        return [
            'ndrEmptyPointValue' => [
                'value' => '0101000000000000000000F87F000000000000F87F',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [],
                    'dimension' => null,
                ],
            ],
            'ndrPointValue' => [
                'value' => '01010000003D0AD7A3701D41400000000000C055C0',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [34.23, -87],
                    'dimension' => null,
                ],
            ],
            'xdrPointValue' => [
                'value' => '000000000140411D70A3D70A3DC055C00000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [34.23, -87],
                    'dimension' => null,
                ],
            ],
            'ndrPointZValue' => [
                'value' => '0101000080000000000000F03F00000000000000400000000000000840',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'Z',
                ],
            ],
            'xdrPointZValue' => [
                'value' => '00800000013FF000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'Z',
                ],
            ],
            'xdrPointZOGCValue' => [
                'value' => '00000003E94117C89F84189375411014361BA5E3540000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [389671.879, 263437.527, 0],
                    'dimension' => 'Z',
                ],
            ],
            'ndrPointMValue' => [
                'value' => '0101000040000000000000F03F00000000000000400000000000000840',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'M',
                ],
            ],
            'xdrPointMValue' => [
                'value' => '00400000013FF000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'M',
                ],
            ],
            'ndrEmptyPointZMValue' => [
                'value' => '01010000C0000000000000F87F000000000000F87F000000000000F87F000000000000F87F',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrEmptyPointZMValue' => [
                'value' => '00C00000017FF80000000000007FF80000000000007FF80000000000007FF8000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrPointZMValue' => [
                'value' => '01010000C0000000000000F03F000000000000004000000000000008400000000000001040',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [1, 2, 3, 4],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrPointZMValue' => [
                'value' => '00C00000013FF0000000000000400000000000000040080000000000004010000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [1, 2, 3, 4],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrPointValueWithSrid' => [
                'value' => '01010000003D0AD7A3701D41400000000000C055C0',
                'expected' => [
                    'srid' => null,
                    'type' => 'POINT',
                    'value' => [34.23, -87],
                    'dimension' => null,
                ],
            ],
            'xdrPointValueWithSrid' => [
                'value' => '0020000001000010E640411D70A3D70A3DC055C00000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [34.23, -87],
                    'dimension' => null,
                ],
            ],
            'ndrPointZValueWithSrid' => [
                'value' => '01010000A0E6100000000000000000F03F00000000000000400000000000000840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'Z',
                ],
            ],
            'xdrPointZValueWithSrid' => [
                'value' => '00A0000001000010E63FF000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'Z',
                ],
            ],
            'ndrPointMValueWithSrid' => [
                'value' => '0101000060e6100000000000000000f03f00000000000000400000000000000840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'M',
                ],
            ],
            'xdrPointMValueWithSrid' => [
                'value' => '0060000001000010e63ff000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'dimension' => 'M',
                ],
            ],
            'ndrEmptyPointZMValueWithSrid' => [
                'value' => '01010000E08C100000000000000000F87F000000000000F87F000000000000F87F000000000000F87F',
                'expected' => [
                    'srid' => 4236,
                    'type' => 'POINT',
                    'value' => [],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrPointZMValueWithSrid' => [
                'value' => '01010000e0e6100000000000000000f03f000000000000004000000000000008400000000000001040',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [1, 2, 3, 4],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrPointZMValueWithSrid' => [
                'value' => '00e0000001000010e63ff0000000000000400000000000000040080000000000004010000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POINT',
                    'value' => [1, 2, 3, 4],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrEmptyLineStringValue' => [
                'value' => '010200000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [],
                    'dimension' => null,
                ],
            ],
            'ndrLineStringValue' => [
                'value' => '0102000000020000003D0AD7A3701D41400000000000C055C06666666666A6464000000000000057C0',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [34.23, -87],
                        [45.3, -92],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrLineStringValue' => [
                'value' => '00000000020000000240411D70A3D70A3DC055C000000000004046A66666666666C057000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [34.23, -87],
                        [45.3, -92],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrLineStringZValue' => [
                'value' => '010200008002000000000000000000000000000000000000000000000000000040000000000000f03f000000000'
                    .'000f03f0000000000000840',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrLineStringZValue' => [
                'value' => '0080000002000000020000000000000000000000000000000040000000000000003ff00000000000003ff000000'
                    .'00000004008000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrLineStringMValue' => [
                'value' => '010200004002000000000000000000000000000000000000000000000000000040000000000000f03f000000000'
                    .'000f03f0000000000000840',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrLineStringMValue' => [
                'value' => '0040000002000000020000000000000000000000000000000040000000000000003ff00000000000003ff000000'
                    .'00000004008000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrLineStringZMValue' => [
                'value' => '01020000c0020000000000000000000000000000000000000000000000000000400000000000000840000000000'
                    .'000f03f000000000000f03f00000000000010400000000000001440',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2, 3],
                        [1, 1, 4, 5],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrLineStringZMValue' => [
                'value' => '00c00000020000000200000000000000000000000000000000400000000000000040080000000000003ff000000'
                    .'00000003ff000000000000040100000000000004014000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2, 3],
                        [1, 1, 4, 5],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrLineStringValueWithSrid' => [
                'value' => '0102000020E6100000020000003D0AD7A3701D41400000000000C055C06666666666A6464000000000000057C0',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [34.23, -87],
                        [45.3, -92],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrLineStringValueWithSrid' => [
                'value' => '0020000002000010E60000000240411D70A3D70A3DC055C000000000004046A66666666666C057000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [34.23, -87],
                        [45.3, -92],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrLineStringZValueWithSrid' => [
                'value' => '01020000a0e610000002000000000000000000000000000000000000000000000000000040000000000000f03f0'
                    .'00000000000f03f0000000000000840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrLineStringZValueWithSrid' => [
                'value' => '00a0000002000010e6000000020000000000000000000000000000000040000000000000003ff00000000000003'
                    .'ff00000000000004008000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrLineStringMValueWithSrid' => [
                'value' => '0102000060e610000002000000000000000000000000000000000000000000000000000040000000000000f03f0'
                    .'00000000000f03f0000000000000840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrLineStringMValueWithSrid' => [
                'value' => '0060000002000010e6000000020000000000000000000000000000000040000000000000003ff00000000000003'
                    .'ff00000000000004008000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2],
                        [1, 1, 3],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrLineStringZMValueWithSrid' => [
                'value' => '01020000e0e61000000200000000000000000000000000000000000000000000000000004000000000000008400'
                    .'00000000000f03f000000000000f03f00000000000010400000000000001440',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2, 3],
                        [1, 1, 4, 5],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrLineStringZMValueWithSrid' => [
                'value' => '00e0000002000010e60000000200000000000000000000000000000000400000000000000040080000000000003'
                    .'ff00000000000003ff000000000000040100000000000004014000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'LINESTRING',
                    'value' => [
                        [0, 0, 2, 3],
                        [1, 1, 4, 5],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrEmptyPolygonValue' => [
                'value' => '010300000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [],
                    'dimension' => null,
                ],
            ],
            'ndrPolygonValue' => [
                'value' => '0103000000010000000500000000000000000000000000000000000000000000000000244000000000000000000'
                    .'00000000000244000000000000024400000000000000000000000000000244000000000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrPolygonValue' => [
                'value' => '0000000003000000010000000500000000000000000000000000000000402400000000000000000000000000004'
                    .'02400000000000040240000000000000000000000000000402400000000000000000000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrPolygonValueWithSrid' => [
                'value' => '0103000020E61000000100000005000000000000000000000000000000000000000000000000002440000000000'
                    .'000000000000000000024400000000000002440000000000000000000000000000024400000000000000000000000000'
                    .'0000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrPolygonValueWithSrid' => [
                'value' => '0020000003000010E60000000100000005000000000000000000000000000000004024000000000000000000000'
                    .'000000040240000000000004024000000000000000000000000000040240000000000000000000000000000000000000'
                    .'0000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiRingPolygonValue' => [
                'value' => '0103000000020000000500000000000000000000000000000000000000000000000000244000000000000000000'
                    .'000000000002440000000000000244000000000000000000000000000002440000000000000000000000000000000000'
                    .'5000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C40000000000'
                    .'0001C4000000000000014400000000000001C4000000000000014400000000000001440',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                            [5, 5],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiRingPolygonValue' => [
                'value' => '0000000003000000020000000500000000000000000000000000000000402400000000000000000000000000004'
                    .'024000000000000402400000000000000000000000000004024000000000000000000000000000000000000000000000'
                    .'000000540140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C00000'
                    .'00000004014000000000000401C00000000000040140000000000004014000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                            [5, 5],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiRingPolygonZValue' => [
                'value' => '0103000080020000000500000000000000000000000000000000000000000000000000f03f00000000000024400'
                    .'000000000000000000000000000004000000000000024400000000000002440000000000000004000000000000000000'
                    .'000000000002440000000000000004000000000000000000000000000000000000000000000f03f05000000000000000'
                    .'000004000000000000000400000000000001440000000000000004000000000000014400000000000001040000000000'
                    .'000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000'
                    .'000004000000000000000400000000000001440',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiRingPolygonZValue' => [
                'value' => '00800000030000000200000005000000000000000000000000000000003ff000000000000040240000000000000'
                    .'000000000000000400000000000000040240000000000004024000000000000400000000000000000000000000000004'
                    .'0240000000000004000000000000000000000000000000000000000000000003ff000000000000000000005400000000'
                    .'000000040000000000000004014000000000000400000000000000040140000000000004010000000000000401400000'
                    .'000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000'
                    .'000000040000000000000004014000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiRingPolygonMValue' => [
                'value' => '0103000040020000000500000000000000000000000000000000000000000000000000f03f00000000000024400'
                    .'000000000000000000000000000004000000000000024400000000000002440000000000000004000000000000000000'
                    .'000000000002440000000000000004000000000000000000000000000000000000000000000f03f05000000000000000'
                    .'000004000000000000000400000000000001440000000000000004000000000000014400000000000001040000000000'
                    .'000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000'
                    .'000004000000000000000400000000000001440',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiRingPolygonMValue' => [
                'value' => '00400000030000000200000005000000000000000000000000000000003ff000000000000040240000000000000'
                    .'000000000000000400000000000000040240000000000004024000000000000400000000000000000000000000000004'
                    .'0240000000000004000000000000000000000000000000000000000000000003ff000000000000000000005400000000'
                    .'000000040000000000000004014000000000000400000000000000040140000000000004010000000000000401400000'
                    .'000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000'
                    .'000000040000000000000004014000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiRingPolygonZMValue' => [
                'value' => '01030000c0020000000500000000000000000000000000000000000000000000000000f03f000000000000f0bf0'
                    .'0000000000024400000000000000000000000000000004000000000000000c0000000000000244000000000000024400'
                    .'00000000000004000000000000000c000000000000000000000000000002440000000000000004000000000000010c00'
                    .'0000000000000000000000000000000000000000000f03f000000000000f0bf050000000000000000000040000000000'
                    .'000004000000000000014400000000000000000000000000000004000000000000014400000000000001040000000000'
                    .'000f03f00000000000014400000000000001440000000000000084000000000000000400000000000001440000000000'
                    .'00000400000000000000840000000000000f03f000000000000004000000000000000400000000000001440000000000'
                    .'0000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1, -1],
                            [10, 0, 2, -2],
                            [10, 10, 2, -2],
                            [0, 10, 2, -4],
                            [0, 0, 1, -1],
                        ],
                        [
                            [2, 2, 5, 0],
                            [2, 5, 4, 1],
                            [5, 5, 3, 2],
                            [5, 2, 3, 1],
                            [2, 2, 5, 0],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiRingPolygonZMValue' => [
                'value' => '00c00000030000000200000005000000000000000000000000000000003ff0000000000000bff00000000000004'
                    .'02400000000000000000000000000004000000000000000c000000000000000402400000000000040240000000000004'
                    .'000000000000000c000000000000000000000000000000040240000000000004000000000000000c0100000000000000'
                    .'00000000000000000000000000000003ff0000000000000bff0000000000000000000054000000000000000400000000'
                    .'0000000401400000000000000000000000000004000000000000000401400000000000040100000000000003ff000000'
                    .'000000040140000000000004014000000000000400800000000000040000000000000004014000000000000400000000'
                    .'000000040080000000000003ff0000000000000400000000000000040000000000000004014000000000000000000000'
                    .'0000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1, -1],
                            [10, 0, 2, -2],
                            [10, 10, 2, -2],
                            [0, 10, 2, -4],
                            [0, 0, 1, -1],
                        ],
                        [
                            [2, 2, 5, 0],
                            [2, 5, 4, 1],
                            [5, 5, 3, 2],
                            [5, 2, 3, 1],
                            [2, 2, 5, 0],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiRingPolygonValueWithSrid' => [
                'value' => '0103000020E61000000200000005000000000000000000000000000000000000000000000000002440000000000'
                    .'000000000000000000024400000000000002440000000000000000000000000000024400000000000000000000000000'
                    .'000000005000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400'
                    .'000000000001C4000000000000014400000000000001C4000000000000014400000000000001440',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                            [5, 5],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiRingPolygonValueWithSrid' => [
                'value' => '0020000003000010E60000000200000005000000000000000000000000000000004024000000000000000000000'
                    .'000000040240000000000004024000000000000000000000000000040240000000000000000000000000000000000000'
                    .'00000000000000540140000000000004014000000000000401C0000000000004014000000000000401C0000000000004'
                    .'01C0000000000004014000000000000401C00000000000040140000000000004014000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                            [0, 0],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                            [5, 5],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiRingPolygonZValueWithSrid' => [
                'value' => '01030000a0e6100000020000000500000000000000000000000000000000000000000000000000f03f000000000'
                    .'000244000000000000000000000000000000040000000000000244000000000000024400000000000000040000000000'
                    .'00000000000000000002440000000000000004000000000000000000000000000000000000000000000f03f050000000'
                    .'000000000000040000000000000004000000000000014400000000000000040000000000000144000000000000010400'
                    .'000000000001440000000000000144000000000000008400000000000001440000000000000004000000000000008400'
                    .'00000000000004000000000000000400000000000001440',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiRingPolygonZValueWithSrid' => [
                'value' => '00a0000003000010e60000000200000005000000000000000000000000000000003ff0000000000000402400000'
                    .'000000000000000000000004000000000000000402400000000000040240000000000004000000000000000000000000'
                    .'000000040240000000000004000000000000000000000000000000000000000000000003ff0000000000000000000054'
                    .'000000000000000400000000000000040140000000000004000000000000000401400000000000040100000000000004'
                    .'014000000000000401400000000000040080000000000004014000000000000400000000000000040080000000000004'
                    .'00000000000000040000000000000004014000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiRingPolygonMValueWithSrid' => [
                'value' => '0103000060e6100000020000000500000000000000000000000000000000000000000000000000f03f000000000'
                    .'000244000000000000000000000000000000040000000000000244000000000000024400000000000000040000000000'
                    .'00000000000000000002440000000000000004000000000000000000000000000000000000000000000f03f050000000'
                    .'000000000000040000000000000004000000000000014400000000000000040000000000000144000000000000010400'
                    .'000000000001440000000000000144000000000000008400000000000001440000000000000004000000000000008400'
                    .'00000000000004000000000000000400000000000001440',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiRingPolygonMValueWithSrid' => [
                'value' => '0060000003000010e60000000200000005000000000000000000000000000000003ff0000000000000402400000'
                    .'000000000000000000000004000000000000000402400000000000040240000000000004000000000000000000000000'
                    .'000000040240000000000004000000000000000000000000000000000000000000000003ff0000000000000000000054'
                    .'000000000000000400000000000000040140000000000004000000000000000401400000000000040100000000000004'
                    .'014000000000000401400000000000040080000000000004014000000000000400000000000000040080000000000004'
                    .'00000000000000040000000000000004014000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1],
                            [10, 0, 2],
                            [10, 10, 2],
                            [0, 10, 2],
                            [0, 0, 1],
                        ],
                        [
                            [2, 2, 5],
                            [2, 5, 4],
                            [5, 5, 3],
                            [5, 2, 3],
                            [2, 2, 5],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiRingPolygonZMValueWithSrid' => [
                'value' => '01030000e0e6100000020000000500000000000000000000000000000000000000000000000000f03f000000000'
                    .'000f0bf00000000000024400000000000000000000000000000004000000000000000c00000000000002440000000000'
                    .'0002440000000000000004000000000000000c0000000000000000000000000000024400000000000000040000000000'
                    .'00010c000000000000000000000000000000000000000000000f03f000000000000f0bf0500000000000000000000400'
                    .'000000000000040000000000000144000000000000000000000000000000040000000000000144000000000000010400'
                    .'00000000000f03f000000000000144000000000000014400000000000000840000000000000004000000000000014400'
                    .'0000000000000400000000000000840000000000000f03f0000000000000040000000000000004000000000000014400'
                    .'000000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1, -1],
                            [10, 0, 2, -2],
                            [10, 10, 2, -2],
                            [0, 10, 2, -4],
                            [0, 0, 1, -1],
                        ],
                        [
                            [2, 2, 5, 0],
                            [2, 5, 4, 1],
                            [5, 5, 3, 2],
                            [5, 2, 3, 1],
                            [2, 2, 5, 0],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiRingPolygonZMValueWithSrid' => [
                'value' => '00e0000003000010e60000000200000005000000000000000000000000000000003ff0000000000000bff000000'
                    .'0000000402400000000000000000000000000004000000000000000c0000000000000004024000000000000402400000'
                    .'00000004000000000000000c000000000000000000000000000000040240000000000004000000000000000c01000000'
                    .'0000000000000000000000000000000000000003ff0000000000000bff00000000000000000000540000000000000004'
                    .'000000000000000401400000000000000000000000000004000000000000000401400000000000040100000000000003'
                    .'ff0000000000000401400000000000040140000000000004008000000000000400000000000000040140000000000004'
                    .'00000000000000040080000000000003ff00000000000004000000000000000400000000000000040140000000000000'
                    .'000000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'POLYGON',
                    'value' => [
                        [
                            [0, 0, 1, -1],
                            [10, 0, 2, -2],
                            [10, 10, 2, -2],
                            [0, 10, 2, -4],
                            [0, 0, 1, -1],
                        ],
                        [
                            [2, 2, 5, 0],
                            [2, 5, 4, 1],
                            [5, 5, 3, 2],
                            [5, 2, 3, 1],
                            [2, 2, 5, 0],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiPointValue' => [
                'value' => '0104000000040000000101000000000000000000000000000000000000000101000000000000000000244000000'
                    .'00000000000010100000000000000000024400000000000002440010100000000000000000000000000000000002440',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0],
                        [10, 0],
                        [10, 10],
                        [0, 10],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiPointValue' => [
                'value' => '0000000004000000040000000001000000000000000000000000000000000000000001402400000000000000000'
                    .'00000000000000000000140240000000000004024000000000000000000000100000000000000004024000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0],
                        [10, 0],
                        [10, 10],
                        [0, 10],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiPointZValue' => [
                'value' => '0104000080020000000101000080000000000000000000000000000000000000000000000000010100008000000'
                    .'000000000400000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 0],
                        [2, 0, 1],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiPointZValue' => [
                'value' => '0080000004000000020080000001000000000000000000000000000000000000000000000000008000000140000'
                    .'0000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 0],
                        [2, 0, 1],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiPointMValue' => [
                'value' => '0104000040020000000101000040000000000000000000000000000000000000000000000040010100004000000'
                    .'000000000400000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 2],
                        [2, 0, 1],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiPointMValue' => [
                'value' => '0040000004000000020040000001000000000000000000000000000000004000000000000000004000000140000'
                    .'0000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 2],
                        [2, 0, 1],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiPointZMValue' => [
                'value' => '01040000c00200000001010000c00000000000000000000000000000f03f0000000000000040000000000000084'
                    .'001010000c000000000000008400000000000000040000000000000f03f0000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 1, 2, 3],
                        [3, 2, 1, 0],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiPointZMValue' => [
                'value' => '00c00000040000000200c000000100000000000000003ff00000000000004000000000000000400800000000000'
                    .'000c0000001400800000000000040000000000000003ff00000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 1, 2, 3],
                        [3, 2, 1, 0],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiPointValueWithSrid' => [
                'value' => '0104000020E61000000400000001010000000000000000000000000000000000000001010000000000000000002'
                    .'440000000000000000001010000000000000000002440000000000000244001010000000000000000000000000000000'
                    .'0002440',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0],
                        [10, 0],
                        [10, 10],
                        [0, 10],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiPointValueWithSrid' => [
                'value' => '0020000004000010E60000000400000000010000000000000000000000000000000000000000014024000000000'
                    .'000000000000000000000000000014024000000000000402400000000000000000000010000000000000000402400000'
                    .'0000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0],
                        [10, 0],
                        [10, 10],
                        [0, 10],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiPointZValueWithSrid' => [
                'value' => '0104000080020000000101000080000000000000000000000000000000000000000000000000010100008000000'
                    .'000000000400000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 0],
                        [2, 0, 1],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiPointZValueWithSrid' => [
                'value' => '0080000004000000020080000001000000000000000000000000000000000000000000000000008000000140000'
                    .'0000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 0],
                        [2, 0, 1],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiPointMValueWithSrid' => [
                'value' => '0104000040020000000101000040000000000000000000000000000000000000000000000040010100004000000'
                    .'000000000400000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 2],
                        [2, 0, 1],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiPointMValueWithSrid' => [
                'value' => '0040000004000000020040000001000000000000000000000000000000004000000000000000004000000140000'
                    .'0000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 0, 2],
                        [2, 0, 1],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiPointZMValueWithSrid' => [
                'value' => '01040000c00200000001010000c00000000000000000000000000000f03f0000000000000040000000000000084'
                    .'001010000c000000000000008400000000000000040000000000000f03f0000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 1, 2, 3],
                        [3, 2, 1, 0],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiPointZMValueWithSrid' => [
                'value' => '00c00000040000000200c000000100000000000000003ff00000000000004000000000000000400800000000000'
                    .'000c0000001400800000000000040000000000000003ff00000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOINT',
                    'value' => [
                        [0, 1, 2, 3],
                        [3, 2, 1, 0],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiLineStringValue' => [
                'value' => '0105000000020000000102000000040000000000000000000000000000000000000000000000000024400000000'
                    .'000000000000000000000244000000000000024400000000000000000000000000000244001020000000400000000000'
                    .'0000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000'
                    .'000000014400000000000001C40',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiLineStringValue' => [
                'value' => '0000000005000000020000000002000000040000000000000000000000000000000040240000000000000000000'
                    .'000000000402400000000000040240000000000000000000000000000402400000000000000000000020000000440140'
                    .'000000000004014000000000000401C0000000000004014000000000000401C000000000000401C00000000000040140'
                    .'00000000000401C000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiLineStringZValue' => [
                'value' => '01050000800200000001020000800200000000000000000000000000000000000000000000000000f03f0000000'
                    .'00000004000000000000000000000000000000040010200008002000000000000000000f03f000000000000f03f00000'
                    .'00000000840000000000000004000000000000000400000000000001040',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiLineStringZValue' => [
                'value' => '008000000500000002008000000200000002000000000000000000000000000000003ff00000000000004000000'
                    .'000000000000000000000000040000000000000000080000002000000023ff00000000000003ff000000000000040080'
                    .'00000000000400000000000000040000000000000004010000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiLineStringMValue' => [
                'value' => '01050000400200000001020000400200000000000000000000000000000000000000000000000000f03f0000000'
                    .'00000004000000000000000000000000000000040010200004002000000000000000000f03f000000000000f03f00000'
                    .'00000000840000000000000004000000000000000400000000000001040',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiLineStringMValue' => [
                'value' => '004000000500000002004000000200000002000000000000000000000000000000003ff00000000000004000000'
                    .'000000000000000000000000040000000000000000040000002000000023ff00000000000003ff000000000000040080'
                    .'00000000000400000000000000040000000000000004010000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiLineStringZMValue' => [
                'value' => '01050000c00200000001020000c00200000000000000000000000000000000000000000000000000f03f0000000'
                    .'000001440000000000000004000000000000000000000000000000040000000000000104001020000c00200000000000'
                    .'0000000f03f000000000000f03f000000000000084000000000000008400000000000000040000000000000004000000'
                    .'000000010400000000000000040',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1, 5],
                            [2, 0, 2, 4],
                        ],
                        [
                            [1, 1, 3, 3],
                            [2, 2, 4, 2],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiLineStringZMValue' => [
                'value' => '00c00000050000000200c000000200000002000000000000000000000000000000003ff00000000000004014000'
                    .'000000000400000000000000000000000000000004000000000000000401000000000000000c0000002000000023ff00'
                    .'000000000003ff0000000000000400800000000000040080000000000004000000000000000400000000000000040100'
                    .'000000000004000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1, 5],
                            [2, 0, 2, 4],
                        ],
                        [
                            [1, 1, 3, 3],
                            [2, 2, 4, 2],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiLineStringValueWithSrid' => [
                'value' => '0105000020E61000000200000001020000000400000000000000000000000000000000000000000000000000244'
                    .'000000000000000000000000000002440000000000000244000000000000000000000000000002440010200000004000'
                    .'000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001'
                    .'C4000000000000014400000000000001C40',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiLineStringValueWithSrid' => [
                'value' => '0020000005000010E60000000200000000020000000400000000000000000000000000000000402400000000000'
                    .'000000000000000004024000000000000402400000000000000000000000000004024000000000000000000000200000'
                    .'00440140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C000000000'
                    .'0004014000000000000401C000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0],
                            [10, 0],
                            [10, 10],
                            [0, 10],
                        ],
                        [
                            [5, 5],
                            [7, 5],
                            [7, 7],
                            [5, 7],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiLineStringZValueWithSrid' => [
                'value' => '01050000a0e61000000200000001020000800200000000000000000000000000000000000000000000000000f03'
                    .'f000000000000004000000000000000000000000000000040010200008002000000000000000000f03f000000000000f'
                    .'03f0000000000000840000000000000004000000000000000400000000000001040',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiLineStringZValueWithSrid' => [
                'value' => '008000000500000002008000000200000002000000000000000000000000000000003ff00000000000004000000'
                    .'000000000000000000000000040000000000000000080000002000000023ff00000000000003ff000000000000040080'
                    .'00000000000400000000000000040000000000000004010000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiLineStringMValueWithSrid' => [
                'value' => '0105000060e61000000200000001020000400200000000000000000000000000000000000000000000000000f03'
                    .'f000000000000004000000000000000000000000000000040010200004002000000000000000000f03f000000000000f'
                    .'03f0000000000000840000000000000004000000000000000400000000000001040',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiLineStringMValueWithSrid' => [
                'value' => '004000000500000002004000000200000002000000000000000000000000000000003ff00000000000004000000'
                    .'000000000000000000000000040000000000000000040000002000000023ff00000000000003ff000000000000040080'
                    .'00000000000400000000000000040000000000000004010000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1],
                            [2, 0, 2],
                        ],
                        [
                            [1, 1, 3],
                            [2, 2, 4],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiLineStringZMValueWithSrid' => [
                'value' => '01050000e0e61000000200000001020000c00200000000000000000000000000000000000000000000000000f03'
                    .'f0000000000001440000000000000004000000000000000000000000000000040000000000000104001020000c002000'
                    .'000000000000000f03f000000000000f03f0000000000000840000000000000084000000000000000400000000000000'
                    .'04000000000000010400000000000000040',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1, 5],
                            [2, 0, 2, 4],
                        ],
                        [
                            [1, 1, 3, 3],
                            [2, 2, 4, 2],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiLineStringZMValueWithSrid' => [
                'value' => '00c00000050000000200c000000200000002000000000000000000000000000000003ff00000000000004014000'
                    .'000000000400000000000000000000000000000004000000000000000401000000000000000c0000002000000023ff00'
                    .'000000000003ff0000000000000400800000000000040080000000000004000000000000000400000000000000040100'
                    .'000000000004000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTILINESTRING',
                    'value' => [
                        [
                            [0, 0, 1, 5],
                            [2, 0, 2, 4],
                        ],
                        [
                            [1, 1, 3, 3],
                            [2, 2, 4, 2],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiPolygonValue' => [
                'value' => '0106000000020000000103000000020000000500000000000000000000000000000000000000000000000000244'
                    .'000000000000000000000000000002440000000000000244000000000000000000000000000002440000000000000000'
                    .'0000000000000000005000000000000000000144000000000000014400000000000001C4000000000000014400000000'
                    .'000001C400000000000001C4000000000000014400000000000001C40000000000000144000000000000014400103000'
                    .'0000100000005000000000000000000F03F000000000000F03F0000000000000840000000000000F03F0000000000000'
                    .'8400000000000000840000000000000F03F0000000000000840000000000000F03F000000000000F03F',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0],
                                [10, 0],
                                [10, 10],
                                [0, 10],
                                [0, 0],
                            ],
                            [
                                [5, 5],
                                [7, 5],
                                [7, 7],
                                [5, 7],
                                [5, 5],
                            ],
                        ],
                        [
                            [
                                [1, 1],
                                [3, 1],
                                [3, 3],
                                [1, 3],
                                [1, 1],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiPolygonValue' => [
                'value' => '0000000006000000020000000003000000020000000500000000000000000000000000000000402400000000000'
                    .'000000000000000004024000000000000402400000000000000000000000000004024000000000000000000000000000'
                    .'000000000000000000000000540140000000000004014000000000000401C0000000000004014000000000000401C000'
                    .'000000000401C0000000000004014000000000000401C000000000000401400000000000040140000000000000000000'
                    .'00300000001000000053FF00000000000003FF000000000000040080000000000003FF00000000000004008000000000'
                    .'00040080000000000003FF000000000000040080000000000003FF00000000000003FF0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0],
                                [10, 0],
                                [10, 10],
                                [0, 10],
                                [0, 0],
                            ],
                            [
                                [5, 5],
                                [7, 5],
                                [7, 7],
                                [5, 7],
                                [5, 5],
                            ],
                        ],
                        [
                            [
                                [1, 1],
                                [3, 1],
                                [3, 3],
                                [1, 3],
                                [1, 1],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiPolygonZValue' => [
                'value' => '0106000080010000000103000080020000000500000000000000000000000000000000000000000000000000084'
                    .'000000000000024400000000000000000000000000000084000000000000024400000000000002440000000000000084'
                    .'000000000000000000000000000002440000000000000084000000000000000000000000000000000000000000000084'
                    .'005000000000000000000004000000000000000400000000000000840000000000000004000000000000014400000000'
                    .'000000840000000000000144000000000000014400000000000000840000000000000144000000000000000400000000'
                    .'000000840000000000000004000000000000000400000000000000840',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiPolygonZValue' => [
                'value' => '0080000006000000010080000003000000020000000500000000000000000000000000000000400800000000000'
                    .'040240000000000000000000000000000400800000000000040240000000000004024000000000000400800000000000'
                    .'000000000000000004024000000000000400800000000000000000000000000000000000000000000400800000000000'
                    .'000000005400000000000000040000000000000004008000000000000400000000000000040140000000000004008000'
                    .'000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000'
                    .'000000000400000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiPolygonMValue' => [
                'value' => '0106000040010000000103000040020000000500000000000000000000000000000000000000000000000000084'
                    .'000000000000024400000000000000000000000000000084000000000000024400000000000002440000000000000084'
                    .'000000000000000000000000000002440000000000000084000000000000000000000000000000000000000000000084'
                    .'005000000000000000000004000000000000000400000000000000840000000000000004000000000000014400000000'
                    .'000000840000000000000144000000000000014400000000000000840000000000000144000000000000000400000000'
                    .'000000840000000000000004000000000000000400000000000000840',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiPolygonMValue' => [
                'value' => '0040000006000000010040000003000000020000000500000000000000000000000000000000400800000000000'
                    .'040240000000000000000000000000000400800000000000040240000000000004024000000000000400800000000000'
                    .'000000000000000004024000000000000400800000000000000000000000000000000000000000000400800000000000'
                    .'000000005400000000000000040000000000000004008000000000000400000000000000040140000000000004008000'
                    .'000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000'
                    .'000000000400000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiPolygonZMValue' => [
                'value' => '01060000c00100000001030000c0020000000500000000000000000000000000000000000000000000000000084'
                    .'000000000000000400000000000002440000000000000000000000000000008400000000000000040000000000000244'
                    .'000000000000024400000000000000840000000000000004000000000000000000000000000002440000000000000084'
                    .'000000000000000400000000000000000000000000000000000000000000008400000000000000040050000000000000'
                    .'000000040000000000000004000000000000008400000000000000040000000000000004000000000000014400000000'
                    .'000000840000000000000004000000000000014400000000000001440000000000000084000000000000000400000000'
                    .'000001440000000000000004000000000000008400000000000000040000000000000004000000000000000400000000'
                    .'0000008400000000000000040',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3, 2],
                                [10, 0, 3, 2],
                                [10, 10, 3, 2],
                                [0, 10, 3, 2],
                                [0, 0, 3, 2],
                            ],
                            [
                                [2, 2, 3, 2],
                                [2, 5, 3, 2],
                                [5, 5, 3, 2],
                                [5, 2, 3, 2],
                                [2, 2, 3, 2],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiPolygonZMValue' => [
                'value' => '00c00000060000000100c0000003000000020000000500000000000000000000000000000000400800000000000'
                    .'040000000000000004024000000000000000000000000000040080000000000004000000000000000402400000000000'
                    .'040240000000000004008000000000000400000000000000000000000000000004024000000000000400800000000000'
                    .'040000000000000000000000000000000000000000000000040080000000000004000000000000000000000054000000'
                    .'000000000400000000000000040080000000000004000000000000000400000000000000040140000000000004008000'
                    .'000000000400000000000000040140000000000004014000000000000400800000000000040000000000000004014000'
                    .'000000000400000000000000040080000000000004000000000000000400000000000000040000000000000004008000'
                    .'0000000004000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3, 2],
                                [10, 0, 3, 2],
                                [10, 10, 3, 2],
                                [0, 10, 3, 2],
                                [0, 0, 3, 2],
                            ],
                            [
                                [2, 2, 3, 2],
                                [2, 5, 3, 2],
                                [5, 5, 3, 2],
                                [5, 2, 3, 2],
                                [2, 2, 3, 2],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiPolygonValueWithSrid' => [
                'value' => '0106000020E61000000200000001030000000200000005000000000000000000000000000000000000000000000'
                    .'000002440000000000000000000000000000024400000000000002440000000000000000000000000000024400000000'
                    .'000000000000000000000000005000000000000000000144000000000000014400000000000001C40000000000000144'
                    .'00000000000001C400000000000001C4000000000000014400000000000001C400000000000001440000000000000144'
                    .'001030000000100000005000000000000000000F03F000000000000F03F0000000000000840000000000000F03F00000'
                    .'000000008400000000000000840000000000000F03F0000000000000840000000000000F03F000000000000F03F',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0],
                                [10, 0],
                                [10, 10],
                                [0, 10],
                                [0, 0],
                            ],
                            [
                                [5, 5],
                                [7, 5],
                                [7, 7],
                                [5, 7],
                                [5, 5],
                            ],
                        ],
                        [
                            [
                                [1, 1],
                                [3, 1],
                                [3, 3],
                                [1, 3],
                                [1, 1],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiPolygonValueWithSrid' => [
                'value' => '0020000006000010E60000000200000000030000000200000005000000000000000000000000000000004024000'
                    .'000000000000000000000000040240000000000004024000000000000000000000000000040240000000000000000000'
                    .'00000000000000000000000000000000540140000000000004014000000000000401C000000000000401400000000000'
                    .'0401C000000000000401C0000000000004014000000000000401C0000000000004014000000000000401400000000000'
                    .'0000000000300000001000000053FF00000000000003FF000000000000040080000000000003FF000000000000040080'
                    .'0000000000040080000000000003FF000000000000040080000000000003FF00000000000003FF0000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0],
                                [10, 0],
                                [10, 10],
                                [0, 10],
                                [0, 0],
                            ],
                            [
                                [5, 5],
                                [7, 5],
                                [7, 7],
                                [5, 7],
                                [5, 5],
                            ],
                        ],
                        [
                            [
                                [1, 1],
                                [3, 1],
                                [3, 3],
                                [1, 3],
                                [1, 1],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiPolygonZValueWithSrid' => [
                'value' => '01060000a0e61000000100000001030000800200000005000000000000000000000000000000000000000000000'
                    .'000000840000000000000244000000000000000000000000000000840000000000000244000000000000024400000000'
                    .'000000840000000000000000000000000000024400000000000000840000000000000000000000000000000000000000'
                    .'000000840050000000000000000000040000000000000004000000000000008400000000000000040000000000000144'
                    .'000000000000008400000000000001440000000000000144000000000000008400000000000001440000000000000004'
                    .'00000000000000840000000000000004000000000000000400000000000000840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiPolygonZValueWithSrid' => [
                'value' => '00a0000006000010e60000000100800000030000000200000005000000000000000000000000000000004008000'
                    .'000000000402400000000000000000000000000004008000000000000402400000000000040240000000000004008000'
                    .'000000000000000000000000040240000000000004008000000000000000000000000000000000000000000004008000'
                    .'000000000000000054000000000000000400000000000000040080000000000004000000000000000401400000000000'
                    .'040080000000000004014000000000000401400000000000040080000000000004014000000000000400000000000000'
                    .'04008000000000000400000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiPolygonMValueWithSrid' => [
                'value' => '0106000060e61000000100000001030000400200000005000000000000000000000000000000000000000000000'
                    .'000000840000000000000244000000000000000000000000000000840000000000000244000000000000024400000000'
                    .'000000840000000000000000000000000000024400000000000000840000000000000000000000000000000000000000'
                    .'000000840050000000000000000000040000000000000004000000000000008400000000000000040000000000000144'
                    .'000000000000008400000000000001440000000000000144000000000000008400000000000001440000000000000004'
                    .'00000000000000840000000000000004000000000000000400000000000000840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiPolygonMValueWithSrid' => [
                'value' => '0060000006000010e60000000100400000030000000200000005000000000000000000000000000000004008000'
                    .'000000000402400000000000000000000000000004008000000000000402400000000000040240000000000004008000'
                    .'000000000000000000000000040240000000000004008000000000000000000000000000000000000000000004008000'
                    .'000000000000000054000000000000000400000000000000040080000000000004000000000000000401400000000000'
                    .'040080000000000004014000000000000401400000000000040080000000000004014000000000000400000000000000'
                    .'04008000000000000400000000000000040000000000000004008000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3],
                                [10, 0, 3],
                                [10, 10, 3],
                                [0, 10, 3],
                                [0, 0, 3],
                            ],
                            [
                                [2, 2, 3],
                                [2, 5, 3],
                                [5, 5, 3],
                                [5, 2, 3],
                                [2, 2, 3],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiPolygonZMValueWithSrid' => [
                'value' => '01060000e0e61000000100000001030000c00200000005000000000000000000000000000000000000000000000'
                    .'000000840000000000000004000000000000024400000000000000000000000000000084000000000000000400000000'
                    .'000002440000000000000244000000000000008400000000000000040000000000000000000000000000024400000000'
                    .'000000840000000000000004000000000000000000000000000000000000000000000084000000000000000400500000'
                    .'000000000000000400000000000000040000000000000084000000000000000400000000000000040000000000000144'
                    .'000000000000008400000000000000040000000000000144000000000000014400000000000000840000000000000004'
                    .'000000000000014400000000000000040000000000000084000000000000000400000000000000040000000000000004'
                    .'000000000000008400000000000000040',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3, 2],
                                [10, 0, 3, 2],
                                [10, 10, 3, 2],
                                [0, 10, 3, 2],
                                [0, 0, 3, 2],
                            ],
                            [
                                [2, 2, 3, 2],
                                [2, 5, 3, 2],
                                [5, 5, 3, 2],
                                [5, 2, 3, 2],
                                [2, 2, 3, 2],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiPolygonZMValueWithSrid' => [
                'value' => '00e0000006000010e60000000100c00000030000000200000005000000000000000000000000000000004008000'
                    .'000000000400000000000000040240000000000000000000000000000400800000000000040000000000000004024000'
                    .'000000000402400000000000040080000000000004000000000000000000000000000000040240000000000004008000'
                    .'000000000400000000000000000000000000000000000000000000000400800000000000040000000000000000000000'
                    .'540000000000000004000000000000000400800000000000040000000000000004000000000000000401400000000000'
                    .'040080000000000004000000000000000401400000000000040140000000000004008000000000000400000000000000'
                    .'040140000000000004000000000000000400800000000000040000000000000004000000000000000400000000000000'
                    .'040080000000000004000000000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [
                                [0, 0, 3, 2],
                                [10, 0, 3, 2],
                                [10, 10, 3, 2],
                                [0, 10, 3, 2],
                                [0, 0, 3, 2],
                            ],
                            [
                                [2, 2, 3, 2],
                                [2, 5, 3, 2],
                                [5, 5, 3, 2],
                                [5, 2, 3, 2],
                                [2, 2, 3, 2],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrEmptyGeometryCollectionValue' => [
                'value' => '010700000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [],
                    'dimension' => null,
                ],
            ],
            'ndrGeometryCollectionValueWithEmptyPoint' => [
                'value' => '0107000000010000000101000000000000000000F87F000000000000F87F',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrGeometryCollectionValue' => [
                'value' => '01070000000300000001010000000000000000002440000000000000244001010000000000000000003E4000000'
                    .'00000003E400102000000020000000000000000002E400000000000002E4000000000000034400000000000003440',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [10, 10],
                        ],
                        [
                            'type' => 'POINT',
                            'value' => [30, 30],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [15, 15],
                                [20, 20],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrGeometryCollectionValue' => [
                'value' => '0000000007000000030000000001402400000000000040240000000000000000000001403E000000000000403E0'
                    .'00000000000000000000200000002402E000000000000402E00000000000040340000000000004034000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [10, 10],
                        ],
                        [
                            'type' => 'POINT',
                            'value' => [30, 30],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [15, 15],
                                [20, 20],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrGeometryCollectionZValue' => [
                'value' => '0107000080030000000101000080000000000000000000000000000000000000000000000000010200008002000'
                    .'000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f'
                    .'03f010700008002000000010100008000000000000000000000000000000000000000000000000001020000800200000'
                    .'0000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03'
                    .'f',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrGeometryCollectionZValue' => [
                'value' => '0080000007000000030080000001000000000000000000000000000000000000000000000000008000000200000'
                    .'0020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000000000'
                    .'000008000000700000002008000000100000000000000000000000000000000000000000000000000800000020000000'
                    .'20000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff000000000000'
                    .'0',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrGeometryCollectionMValue' => [
                'value' => '0107000040030000000101000040000000000000000000000000000000000000000000000000010200004002000'
                    .'000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f'
                    .'03f010700004002000000010100004000000000000000000000000000000000000000000000000001020000400200000'
                    .'0000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03'
                    .'f',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrGeometryCollectionMValue' => [
                'value' => '0040000007000000030040000001000000000000000000000000000000000000000000000000004000000200000'
                    .'0020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000000000'
                    .'000004000000700000002004000000100000000000000000000000000000000000000000000000000400000020000000'
                    .'20000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff000000000000'
                    .'0',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrGeometryCollectionZMValue' => [
                'value' => '01070000c00300000001010000c0000000000000000000000000000000000000000000000000000000000000f03'
                    .'f01020000c0020000000000000000000000000000000000000000000000000000000000000000000040000000000000f'
                    .'03f000000000000f03f000000000000f03f000000000000084001070000c00200000001010000c000000000000000000'
                    .'0000000000000000000000000000000000000000000104001020000c0020000000000000000000000000000000000000'
                    .'000000000000000000000000000001440000000000000f03f000000000000f03f000000000000f03f000000000000184'
                    .'0',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0, 1],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0, 2],
                                [1, 1, 1, 3],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0, 4],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0, 5],
                                        [1, 1, 1, 6],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrGeometryCollectionZMValue' => [
                'value' => '00c00000070000000300c00000010000000000000000000000000000000000000000000000003ff000000000000'
                    .'000c00000020000000200000000000000000000000000000000000000000000000040000000000000003ff0000000000'
                    .'0003ff00000000000003ff0000000000000400800000000000000c00000070000000200c000000100000000000000000'
                    .'0000000000000000000000000000000401000000000000000c0000002000000020000000000000000000000000000000'
                    .'0000000000000000040140000000000003ff00000000000003ff00000000000003ff0000000000000401800000000000'
                    .'0',
                'expected' => [
                    'srid' => null,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0, 1],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0, 2],
                                [1, 1, 1, 3],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0, 4],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0, 5],
                                        [1, 1, 1, 6],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrGeometryCollectionValueWithSrid' => [
                'value' => '0107000020E61000000300000001010000000000000000002440000000000000244001010000000000000000003'
                    .'E400000000000003E400102000000020000000000000000002E400000000000002E40000000000000344000000000000'
                    .'03440',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [10, 10],
                        ],
                        [
                            'type' => 'POINT',
                            'value' => [30, 30],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [15, 15],
                                [20, 20],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrGeometryCollectionValueWithSrid' => [
                'value' => '0020000007000010E6000000030000000001402400000000000040240000000000000000000001403E000000000'
                    .'000403E000000000000000000000200000002402E000000000000402E000000000000403400000000000040340000000'
                    .'00000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [10, 10],
                        ],
                        [
                            'type' => 'POINT',
                            'value' => [30, 30],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [15, 15],
                                [20, 20],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrGeometryCollectionZValueWithSrid' => [
                'value' => '01070000a0e61000000300000001010000800000000000000000000000000000000000000000000000000102000'
                    .'08002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f00000'
                    .'0000000f03f0107000080020000000101000080000000000000000000000000000000000000000000000000010200008'
                    .'002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f0000000'
                    .'00000f03f',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrGeometryCollectionZValueWithSrid' => [
                'value' => '00a0000007000010e60000000300800000010000000000000000000000000000000000000000000000000080000'
                    .'002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff00'
                    .'000000000000080000007000000020080000001000000000000000000000000000000000000000000000000008000000'
                    .'2000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000'
                    .'000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrGeometryCollectionMValueWithSrid' => [
                'value' => '0107000060e61000000300000001010000400000000000000000000000000000000000000000000000000102000'
                    .'04002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f00000'
                    .'0000000f03f0107000040020000000101000040000000000000000000000000000000000000000000000000010200004'
                    .'002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f0000000'
                    .'00000f03f',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrGeometryCollectionMValueWithSrid' => [
                'value' => '0060000007000010e60000000300400000010000000000000000000000000000000000000000000000000040000'
                    .'002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff00'
                    .'000000000000040000007000000020040000001000000000000000000000000000000000000000000000000004000000'
                    .'2000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000'
                    .'000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0],
                                [1, 1, 1],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0],
                                        [1, 1, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrGeometryCollectionZMValueWithSrid' => [
                'value' => '01070000e0e61000000300000001010000c00000000000000000000000000000000000000000000000000000000'
                    .'00000f03f01020000c002000000000000000000000000000000000000000000000000000000000000000000004000000'
                    .'0000000f03f000000000000f03f000000000000f03f000000000000084001070000c00200000001010000c0000000000'
                    .'000000000000000000000000000000000000000000000000000104001020000c00200000000000000000000000000000'
                    .'00000000000000000000000000000000000001440000000000000f03f000000000000f03f000000000000f03f0000000'
                    .'000001840',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0, 1],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0, 2],
                                [1, 1, 1, 3],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0, 4],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0, 5],
                                        [1, 1, 1, 6],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrGeometryCollectionZMValueWithSrid' => [
                'value' => '00e0000007000010e60000000300c00000010000000000000000000000000000000000000000000000003ff0000'
                    .'00000000000c00000020000000200000000000000000000000000000000000000000000000040000000000000003ff00'
                    .'000000000003ff00000000000003ff0000000000000400800000000000000c00000070000000200c0000001000000000'
                    .'000000000000000000000000000000000000000401000000000000000c00000020000000200000000000000000000000'
                    .'000000000000000000000000040140000000000003ff00000000000003ff00000000000003ff00000000000004018000'
                    .'000000000',
                'expected' => [
                    'srid' => 4326,
                    'type' => 'GEOMETRYCOLLECTION',
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 0, 0, 1],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [0, 0, 0, 2],
                                [1, 1, 1, 3],
                            ],
                        ],
                        [
                            'type' => 'GEOMETRYCOLLECTION',
                            'value' => [
                                [
                                    'type' => 'POINT',
                                    'value' => [0, 0, 0, 4],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [0, 0, 0, 5],
                                        [1, 1, 1, 6],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrCircularStringValue' => [
                'value' => '01080000000300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000'
                    .'00000400000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0],
                        [1, 1],
                        [2, 0],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrCircularStringValue' => [
                'value' => '000000000800000003000000000000000000000000000000003ff00000000000003ff0000000000000400000000'
                    .'00000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0],
                        [1, 1],
                        [2, 0],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrCircularStringZValue' => [
                'value' => '01080000800300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000'
                    .'000f03f000000000000f03f00000000000000400000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0, 1],
                        [1, 1, 1],
                        [2, 0, 1],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrCircularStringZValue' => [
                'value' => '008000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff000000'
                    .'00000003ff0000000000000400000000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0, 1],
                        [1, 1, 1],
                        [2, 0, 1],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrCircularStringMValue' => [
                'value' => '01080000400300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000'
                    .'000f03f000000000000f03f00000000000000400000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0, 1],
                        [1, 1, 1],
                        [2, 0, 1],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrCircularStringMValue' => [
                'value' => '004000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff000000'
                    .'00000003ff0000000000000400000000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0, 1],
                        [1, 1, 1],
                        [2, 0, 1],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrCircularStringZMValue' => [
                'value' => '01080000c00300000000000000000000000000000000000000000000000000f03f0000000000000040000000000'
                    .'000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000000000000000000000'
                    .'000f03f0000000000000040',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0, 1, 2],
                        [1, 1, 1, 2],
                        [2, 0, 1, 2],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrCircularStringZMValue' => [
                'value' => '00c000000800000003000000000000000000000000000000003ff000000000000040000000000000003ff000000'
                    .'00000003ff00000000000003ff00000000000004000000000000000400000000000000000000000000000003ff000000'
                    .'00000004000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CIRCULARSTRING',
                    'value' => [
                        [0, 0, 1, 2],
                        [1, 1, 1, 2],
                        [2, 0, 1, 2],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrCompoundCurveValue' => [
                'value' => '01090000000200000001080000000300000000000000000000000000000000000000000000000000f03f0000000'
                    .'00000f03f000000000000004000000000000000000102000000020000000000000000000040000000000000000000000'
                    .'00000001040000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0],
                                [1, 1],
                                [2, 0],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0],
                                [4, 1],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrCompoundCurveValue' => [
                'value' => '000000000900000002000000000800000003000000000000000000000000000000003ff00000000000003ff0000'
                    .'000000000400000000000000000000000000000000000000002000000024000000000000000000000000000000040100'
                    .'000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0],
                                [1, 1],
                                [2, 0],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0],
                                [4, 1],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrCompoundCurveZValue' => [
                'value' => '01090000800200000001080000800300000000000000000000000000000000000000000000000000f03f0000000'
                    .'00000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f0102000'
                    .'080020000000000000000000040000000000000000000000000000000000000000000001040000000000000f03f00000'
                    .'0000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0, 1],
                                [1, 1, 1],
                                [2, 0, 1],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0, 0],
                                [4, 1, 1],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrCompoundCurveZValue' => [
                'value' => '008000000900000002008000000800000003000000000000000000000000000000003ff00000000000003ff0000'
                    .'0000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff00000000000000080000'
                    .'0020000000240000000000000000000000000000000000000000000000040100000000000003ff00000000000003ff00'
                    .'00000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0, 1],
                                [1, 1, 1],
                                [2, 0, 1],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0, 0],
                                [4, 1, 1],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrCompoundCurveMValue' => [
                'value' => '01090000400200000001080000400300000000000000000000000000000000000000000000000000f03f0000000'
                    .'00000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f0102000'
                    .'040020000000000000000000040000000000000000000000000000000000000000000001040000000000000f03f00000'
                    .'0000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0, 1],
                                [1, 1, 1],
                                [2, 0, 1],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0, 0],
                                [4, 1, 1],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrCompoundCurveMValue' => [
                'value' => '004000000900000002004000000800000003000000000000000000000000000000003ff00000000000003ff0000'
                    .'0000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff00000000000000040000'
                    .'0020000000240000000000000000000000000000000000000000000000040100000000000003ff00000000000003ff00'
                    .'00000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0, 1],
                                [1, 1, 1],
                                [2, 0, 1],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0, 0],
                                [4, 1, 1],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrCompoundCurveZMValue' => [
                'value' => '01090000c00200000001080000c00300000000000000000000000000000000000000000000000000f03f0000000'
                    .'000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000'
                    .'000000000000000000000f03f000000000000004001020000c0020000000000000000000040000000000000000000000'
                    .'0000000000000000000000000000000000000001040000000000000f03f000000000000f03f000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0, 1, 2],
                                [1, 1, 1, 2],
                                [2, 0, 1, 2],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0, 0, 0],
                                [4, 1, 1, 1],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrCompoundCurveZMValue' => [
                'value' => '00c00000090000000200c000000800000003000000000000000000000000000000003ff00000000000004000000'
                    .'0000000003ff00000000000003ff00000000000003ff0000000000000400000000000000040000000000000000000000'
                    .'0000000003ff0000000000000400000000000000000c0000002000000024000000000000000000000000000000000000'
                    .'00000000000000000000000000040100000000000003ff00000000000003ff00000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'COMPOUNDCURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0, 1, 2],
                                [1, 1, 1, 2],
                                [2, 0, 1, 2],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 0, 0, 0],
                                [4, 1, 1, 1],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrCurvePolygonValue' => [
                'value' => '010A000000020000000108000000030000000000000000000000000000000000000000000000000008400000000'
                    .'0000008400000000000001C400000000000001C400102000000030000000000000000001C400000000000001C4000000'
                    .'00000002040000000000000204000000000000022400000000000002240',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0],
                                [3, 3],
                                [7, 7],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [7, 7],
                                [8, 8],
                                [9, 9],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrCurvePolygonCompoundCurveValue' => [
                'value' => '010a000000010000000109000000020000000108000000030000000000000000000000000000000000000000000'
                    .'0000000f03f000000000000f03f000000000000004000000000000000000102000000030000000000000000000040000'
                    .'0000000000000000000000000f03f000000000000f0bf00000000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0],
                                        [1, 1],
                                        [2, 0],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0],
                                        [1, -1],
                                        [0, 0],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrCurvePolygonCompoundCurveValue' => [
                'value' => '000000000a00000001000000000900000002000000000800000003000000000000000000000000000000003ff00'
                    .'000000000003ff0000000000000400000000000000000000000000000000000000002000000034000000000000000000'
                    .'00000000000003ff0000000000000bff000000000000000000000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0],
                                        [1, 1],
                                        [2, 0],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0],
                                        [1, -1],
                                        [0, 0],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrCurvePolygonZCompoundCurveValue' => [
                'value' => '010a000080010000000109000080020000000108000080030000000000000000000000000000000000000000000'
                    .'0000000f03f000000000000f03f000000000000f03f000000000000f03f0000000000000040000000000000000000000'
                    .'0000000f03f01020000800300000000000000000000400000000000000000000000000000f03f000000000000f03f000'
                    .'000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrCurvePolygonZCompoundCurveValue' => [
                'value' => '008000000a00000001008000000900000002008000000800000003000000000000000000000000000000003ff00'
                    .'000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff00'
                    .'00000000000008000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff'
                    .'00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrCurvePolygonMCompoundCurveValue' => [
                'value' => '010a000040010000000109000040020000000108000040030000000000000000000000000000000000000000000'
                    .'0000000f03f000000000000f03f000000000000f03f000000000000f03f0000000000000040000000000000000000000'
                    .'0000000f03f01020000400300000000000000000000400000000000000000000000000000f03f000000000000f03f000'
                    .'000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrCurvePolygonMCompoundCurveValue' => [
                'value' => '004000000a00000001004000000900000002004000000800000003000000000000000000000000000000003ff00'
                    .'000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff00'
                    .'00000000000004000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff'
                    .'00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrCurvePolygonZMCompoundCurveValue' => [
                'value' => '010a0000c00100000001090000c00200000001080000c0030000000000000000000000000000000000000000000'
                    .'0000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000'
                    .'000000000400000000000000000000000000000f03f000000000000004001020000c0030000000000000000000040000'
                    .'0000000000000000000000000f03f0000000000000040000000000000f03f000000000000f0bf000000000000f03f000'
                    .'000000000f03f00000000000000000000000000000000000000000000f03f0000000000000040',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1, 2],
                                        [1, 1, 1, 2],
                                        [2, 0, 1, 2],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1, 2],
                                        [1, -1, 1, 1],
                                        [0, 0, 1, 2],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrCurvePolygonZMVCompoundCurvealue' => [
                'value' => '00c000000a0000000100c00000090000000200c000000800000003000000000000000000000000000000003ff00'
                    .'0000000000040000000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000040000'
                    .'0000000000000000000000000003ff0000000000000400000000000000000c0000002000000034000000000000000000'
                    .'00000000000003ff000000000000040000000000000003ff0000000000000bff00000000000003ff00000000000003ff'
                    .'0000000000000000000000000000000000000000000003ff00000000000004000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'CURVEPOLYGON',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1, 2],
                                        [1, 1, 1, 2],
                                        [2, 0, 1, 2],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1, 2],
                                        [1, -1, 1, 1],
                                        [0, 0, 1, 2],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiCurveValue' => [
                'value' => '010B000000020000000108000000030000000000000000000000000000000000000000000000000008400000000'
                    .'0000008400000000000001C400000000000001C400102000000030000000000000000001C400000000000001C4000000'
                    .'00000002040000000000000204000000000000022400000000000002240',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'CIRCULARSTRING',
                            'value' => [
                                [0, 0],
                                [3, 3],
                                [7, 7],
                            ],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [7, 7],
                                [8, 8],
                                [9, 9],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiCurveCompoundCurveValue' => [
                'value' => '010b000000010000000109000000020000000108000000030000000000000000000000000000000000000000000'
                    .'0000000f03f000000000000f03f000000000000004000000000000000000102000000030000000000000000000040000'
                    .'0000000000000000000000000f03f000000000000f0bf00000000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0],
                                        [1, 1],
                                        [2, 0],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0],
                                        [1, -1],
                                        [0, 0],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiCurveCompoundCurveValue' => [
                'value' => '000000000b00000001000000000900000002000000000800000003000000000000000000000000000000003ff00'
                    .'000000000003ff0000000000000400000000000000000000000000000000000000002000000034000000000000000000'
                    .'00000000000003ff0000000000000bff000000000000000000000000000000000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0],
                                        [1, 1],
                                        [2, 0],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0],
                                        [1, -1],
                                        [0, 0],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiCurveZCompoundCurveValue' => [
                'value' => '010b000080010000000109000080020000000108000080030000000000000000000000000000000000000000000'
                    .'0000000f03f000000000000f03f000000000000f03f000000000000f03f0000000000000040000000000000000000000'
                    .'0000000f03f01020000800300000000000000000000400000000000000000000000000000f03f000000000000f03f000'
                    .'000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiCurveZCompoundCurveValue' => [
                'value' => '008000000b00000001008000000900000002008000000800000003000000000000000000000000000000003ff00'
                    .'000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff00'
                    .'00000000000008000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff'
                    .'00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiCurveMCompoundCurveValue' => [
                'value' => '010b000040010000000109000040020000000108000040030000000000000000000000000000000000000000000'
                    .'0000000f03f000000000000f03f000000000000f03f000000000000f03f0000000000000040000000000000000000000'
                    .'0000000f03f01020000400300000000000000000000400000000000000000000000000000f03f000000000000f03f000'
                    .'000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiCurveMCompoundCurveValue' => [
                'value' => '004000000b00000001004000000900000002004000000800000003000000000000000000000000000000003ff00'
                    .'000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff00'
                    .'00000000000004000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff'
                    .'00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1],
                                        [1, 1, 1],
                                        [2, 0, 1],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1],
                                        [1, -1, 1],
                                        [0, 0, 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiCurveZMCompoundCurveValue' => [
                'value' => '010b0000c00100000001090000c00200000001080000c0030000000000000000000000000000000000000000000'
                    .'0000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000'
                    .'000000000400000000000000000000000000000f03f000000000000004001020000c0030000000000000000000040000'
                    .'0000000000000000000000000f03f0000000000000040000000000000f03f000000000000f0bf000000000000f03f000'
                    .'000000000f03f00000000000000000000000000000000000000000000f03f0000000000000040',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1, 2],
                                        [1, 1, 1, 2],
                                        [2, 0, 1, 2],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1, 2],
                                        [1, -1, 1, 1],
                                        [0, 0, 1, 2],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiCurveZMCompoundCurveValue' => [
                'value' => '00c000000b0000000100c00000090000000200c000000800000003000000000000000000000000000000003ff00'
                    .'0000000000040000000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000040000'
                    .'0000000000000000000000000003ff0000000000000400000000000000000c0000002000000034000000000000000000'
                    .'00000000000003ff000000000000040000000000000003ff0000000000000bff00000000000003ff00000000000003ff'
                    .'0000000000000000000000000000000000000000000003ff00000000000004000000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTICURVE',
                    'value' => [
                        [
                            'type' => 'COMPOUNDCURVE',
                            'value' => [
                                [
                                    'type' => 'CIRCULARSTRING',
                                    'value' => [
                                        [0, 0, 1, 2],
                                        [1, 1, 1, 2],
                                        [2, 0, 1, 2],
                                    ],
                                ],
                                [
                                    'type' => 'LINESTRING',
                                    'value' => [
                                        [2, 0, 1, 2],
                                        [1, -1, 1, 1],
                                        [0, 0, 1, 2],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrMultiSurfaceValue' => [
                'value' => '010c00000002000000010a000000010000000109000000020000000108000000030000000000000000000000000'
                    .'0000000000000000000000000f03f000000000000f03f000000000000004000000000000000000102000000030000000'
                    .'0000000000000400000000000000000000000000000f03f000000000000f0bf000000000000000000000000000000000'
                    .'103000000010000000500000000000000000024400000000000002440000000000000244000000000000028400000000'
                    .'00000284000000000000028400000000000002840000000000000244000000000000024400000000000002440',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0],
                                                [1, 1],
                                                [2, 0],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0],
                                                [1, -1],
                                                [0, 0],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10],
                                    [10, 12],
                                    [12, 12],
                                    [12, 10],
                                    [10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiSurfaceValue' => [
                'value' => '000000000c00000002000000000a000000010000000009000000020000000008000000030000000000000000000'
                    .'00000000000003ff00000000000003ff0000000000000400000000000000000000000000000000000000002000000034'
                    .'00000000000000000000000000000003ff0000000000000bff0000000000000000000000000000000000000000000000'
                    .'000000003000000010000000540240000000000004024000000000000402400000000000040280000000000004028000'
                    .'00000000040280000000000004028000000000000402400000000000040240000000000004024000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0],
                                                [1, 1],
                                                [2, 0],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0],
                                                [1, -1],
                                                [0, 0],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10],
                                    [10, 12],
                                    [12, 12],
                                    [12, 10],
                                    [10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'ndrMultiSurfaceZValue' => [
                'value' => '010c00008002000000010a000080010000000109000080020000000108000080030000000000000000000000000'
                    .'0000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f0000000000000040000'
                    .'0000000000000000000000000f03f01020000800300000000000000000000400000000000000000000000000000f03f0'
                    .'00000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f0'
                    .'103000080010000000500000000000000000024400000000000002440000000000000244000000000000024400000000'
                    .'000002840000000000000244000000000000028400000000000002840000000000000244000000000000028400000000'
                    .'0000024400000000000002440000000000000244000000000000024400000000000002440',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0, 1],
                                                [1, 1, 1],
                                                [2, 0, 1],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0, 1],
                                                [1, -1, 1],
                                                [0, 0, 1],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10, 10],
                                    [10, 12, 10],
                                    [12, 12, 10],
                                    [12, 10, 10],
                                    [10, 10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiSurfaceZValue' => [
                'value' => '008000000c00000002008000000a000000010080000009000000020080000008000000030000000000000000000'
                    .'00000000000003ff00000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000000'
                    .'00000000000003ff0000000000000008000000200000003400000000000000000000000000000003ff00000000000003'
                    .'ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff00000000000000'
                    .'080000003000000010000000540240000000000004024000000000000402400000000000040240000000000004028000'
                    .'000000000402400000000000040280000000000004028000000000000402400000000000040280000000000004024000'
                    .'0000000004024000000000000402400000000000040240000000000004024000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0, 1],
                                                [1, 1, 1],
                                                [2, 0, 1],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0, 1],
                                                [1, -1, 1],
                                                [0, 0, 1],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10, 10],
                                    [10, 12, 10],
                                    [12, 12, 10],
                                    [12, 10, 10],
                                    [10, 10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrMultiSurfaceMValue' => [
                'value' => '010c00004002000000010a000040010000000109000040020000000108000040030000000000000000000000000'
                    .'0000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f0000000000000040000'
                    .'0000000000000000000000000f03f01020000400300000000000000000000400000000000000000000000000000f03f0'
                    .'00000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f0'
                    .'103000040010000000500000000000000000024400000000000002440000000000000244000000000000024400000000'
                    .'000002840000000000000244000000000000028400000000000002840000000000000244000000000000028400000000'
                    .'0000024400000000000002440000000000000244000000000000024400000000000002440',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0, 1],
                                                [1, 1, 1],
                                                [2, 0, 1],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0, 1],
                                                [1, -1, 1],
                                                [0, 0, 1],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10, 10],
                                    [10, 12, 10],
                                    [12, 12, 10],
                                    [12, 10, 10],
                                    [10, 10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrMultiSurfaceMValue' => [
                'value' => '004000000c00000002004000000a000000010040000009000000020040000008000000030000000000000000000'
                    .'00000000000003ff00000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000000'
                    .'00000000000003ff0000000000000004000000200000003400000000000000000000000000000003ff00000000000003'
                    .'ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff00000000000000'
                    .'040000003000000010000000540240000000000004024000000000000402400000000000040240000000000004028000'
                    .'000000000402400000000000040280000000000004028000000000000402400000000000040280000000000004024000'
                    .'0000000004024000000000000402400000000000040240000000000004024000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0, 1],
                                                [1, 1, 1],
                                                [2, 0, 1],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0, 1],
                                                [1, -1, 1],
                                                [0, 0, 1],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10, 10],
                                    [10, 12, 10],
                                    [12, 12, 10],
                                    [12, 10, 10],
                                    [10, 10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'ndrMultiSurfaceZMValue' => [
                'value' => '010c0000c002000000010a0000c00100000001090000c00200000001080000c0030000000000000000000000000'
                    .'0000000000000000000000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000'
                    .'000000000004000000000000000400000000000000000000000000000f03f000000000000004001020000c0030000000'
                    .'0000000000000400000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f0bf0'
                    .'00000000000f03f000000000000f03f00000000000000000000000000000000000000000000f03f00000000000000400'
                    .'1030000c0010000000500000000000000000024400000000000002440000000000000244000000000000024400000000'
                    .'000002440000000000000284000000000000024400000000000002440000000000000284000000000000028400000000'
                    .'000002440000000000000244000000000000028400000000000002440000000000000244000000000000024400000000'
                    .'000002440000000000000244000000000000024400000000000002440',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0, 1, 2],
                                                [1, 1, 1, 2],
                                                [2, 0, 1, 2],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0, 1, 2],
                                                [1, -1, 1, 1],
                                                [0, 0, 1, 2],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10, 10, 10],
                                    [10, 12, 10, 10],
                                    [12, 12, 10, 10],
                                    [12, 10, 10, 10],
                                    [10, 10, 10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'xdrMultiSurfaceZMValue' => [
                'value' => '00c000000c0000000200c000000a0000000100c00000090000000200c0000008000000030000000000000000000'
                    .'00000000000003ff000000000000040000000000000003ff00000000000003ff00000000000003ff0000000000000400'
                    .'0000000000000400000000000000000000000000000003ff0000000000000400000000000000000c0000002000000034'
                    .'00000000000000000000000000000003ff000000000000040000000000000003ff0000000000000bff00000000000003'
                    .'ff00000000000003ff0000000000000000000000000000000000000000000003ff000000000000040000000000000000'
                    .'0c0000003000000010000000540240000000000004024000000000000402400000000000040240000000000004024000'
                    .'000000000402800000000000040240000000000004024000000000000402800000000000040280000000000004024000'
                    .'000000000402400000000000040280000000000004024000000000000402400000000000040240000000000004024000'
                    .'000000000402400000000000040240000000000004024000000000000',
                'expected' => [
                    'srid' => null,
                    'type' => 'MULTISURFACE',
                    'value' => [
                        [
                            'type' => 'CURVEPOLYGON',
                            'value' => [
                                [
                                    'type' => 'COMPOUNDCURVE',
                                    'value' => [
                                        [
                                            'type' => 'CIRCULARSTRING',
                                            'value' => [
                                                [0, 0, 1, 2],
                                                [1, 1, 1, 2],
                                                [2, 0, 1, 2],
                                            ],
                                        ],
                                        [
                                            'type' => 'LINESTRING',
                                            'value' => [
                                                [2, 0, 1, 2],
                                                [1, -1, 1, 1],
                                                [0, 0, 1, 2],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 10, 10, 10],
                                    [10, 12, 10, 10],
                                    [12, 12, 10, 10],
                                    [12, 10, 10, 10],
                                    [10, 10, 10, 10],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'ZM',
                ],
            ],
            'ndrPolyhedralSurfaceZValue' => [
                'value' => '010f000080050000000103000080010000000500000000000000000000000000000000000000000000000000000'
                    .'000000000000000000000000000000000000000000000144000000000000000000000000000002e40000000000000144'
                    .'000000000000000000000000000002e40000000000000000000000000000000000000000000000000000000000000000'
                    .'001030000800100000005000000000000000000000000000000000000000000000000000000000000000000000000000'
                    .'00000002e40000000000000000000000000000024400000000000002e400000000000000000000000000000244000000'
                    .'000000000000000000000000000000000000000000000000000000000000000000000000000010300008001000000050'
                    .'000000000000000000000000000000000000000000000000000000000000000002440000000000000000000000000000'
                    .'000000000000000002440000000000000000000000000000014400000000000000000000000000000000000000000000'
                    .'014400000000000000000000000000000000000000000000000000103000080010000000500000000000000000024400'
                    .'000000000000000000000000000000000000000000024400000000000002e40000000000000000000000000000024400'
                    .'000000000002e40000000000000144000000000000024400000000000000000000000000000144000000000000024400'
                    .'00000000000000000000000000000000103000080010000000500000000000000000000000000000000002e400000000'
                    .'00000000000000000000000000000000000002e40000000000000144000000000000024400000000000002e400000000'
                    .'00000144000000000000024400000000000002e40000000000000000000000000000000000000000000002e400000000'
                    .'000000000',
                'expected' => [
                    'type' => 'POLYHEDRALSURFACE',
                    'srid' => null,
                    'value' => [
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0, 0],
                                    [0, 0, 5],
                                    [0, 15, 5],
                                    [0, 15, 0],
                                    [0, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0, 0],
                                    [0, 15, 0],
                                    [10, 15, 0],
                                    [10, 0, 0],
                                    [0, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0, 0],
                                    [10, 0, 0],
                                    [10, 0, 5],
                                    [0, 0, 5],
                                    [0, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 0, 0],
                                    [10, 15, 0],
                                    [10, 15, 5],
                                    [10, 0, 5],
                                    [10, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 15, 0],
                                    [0, 15, 5],
                                    [10, 15, 5],
                                    [10, 15, 0],
                                    [0, 15, 0],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'Z',
                ],
            ],
            'ndrPolyhedralSurfaceMValue' => [
                'value' => '010f000040050000000103000040010000000500000000000000000000000000000000000000000000000000000'
                    .'000000000000000000000000000000000000000000000144000000000000000000000000000002e40000000000000144'
                    .'000000000000000000000000000002e40000000000000000000000000000000000000000000000000000000000000000'
                    .'001030000400100000005000000000000000000000000000000000000000000000000000000000000000000000000000'
                    .'00000002e40000000000000000000000000000024400000000000002e400000000000000000000000000000244000000'
                    .'000000000000000000000000000000000000000000000000000000000000000000000000000010300004001000000050'
                    .'000000000000000000000000000000000000000000000000000000000000000002440000000000000000000000000000'
                    .'000000000000000002440000000000000000000000000000014400000000000000000000000000000000000000000000'
                    .'014400000000000000000000000000000000000000000000000000103000040010000000500000000000000000024400'
                    .'000000000000000000000000000000000000000000024400000000000002e40000000000000000000000000000024400'
                    .'000000000002e40000000000000144000000000000024400000000000000000000000000000144000000000000024400'
                    .'00000000000000000000000000000000103000040010000000500000000000000000000000000000000002e400000000'
                    .'00000000000000000000000000000000000002e40000000000000144000000000000024400000000000002e400000000'
                    .'00000144000000000000024400000000000002e40000000000000000000000000000000000000000000002e400000000'
                    .'000000000',
                'expected' => [
                    'type' => 'POLYHEDRALSURFACE',
                    'srid' => null,
                    'value' => [
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0, 0],
                                    [0, 0, 5],
                                    [0, 15, 5],
                                    [0, 15, 0],
                                    [0, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0, 0],
                                    [0, 15, 0],
                                    [10, 15, 0],
                                    [10, 0, 0],
                                    [0, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0, 0],
                                    [10, 0, 0],
                                    [10, 0, 5],
                                    [0, 0, 5],
                                    [0, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [10, 0, 0],
                                    [10, 15, 0],
                                    [10, 15, 5],
                                    [10, 0, 5],
                                    [10, 0, 0],
                                ],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 15, 0],
                                    [0, 15, 5],
                                    [10, 15, 5],
                                    [10, 15, 0],
                                    [0, 15, 0],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => 'M',
                ],
            ],
            'xdrGeometryCollectionValue2' => [
                'value' => '01070000000600000001010000000000000000000000000000000000f03f010200000002000000000000000000004000000000000008400000000000001040000000000000144001030000000200000005000000000000000000000000000000000000000000000000000000000000000000244000000000000024400000000000002440000000000000244000000000000000000000000000000000000000000000000005000000000000000000f03f000000000000f03f000000000000f03f0000000000002240000000000000224000000000000022400000000000002240000000000000f03f000000000000f03f000000000000f03f01040000000200000001010000000000000000000000000000000000f03f0101000000000000000000004000000000000008400105000000020000000102000000020000000000000000000000000000000000f03f000000000000004000000000000008400102000000020000000000000000001040000000000000144000000000000018400000000000001c4001060000000200000001030000000200000005000000000000000000000000000000000000000000000000000000000000000000244000000000000024400000000000002440000000000000244000000000000000000000000000000000000000000000000005000000000000000000f03f000000000000f03f000000000000f03f0000000000002240000000000000224000000000000022400000000000002240000000000000f03f000000000000f03f000000000000f03f0103000000010000000500000000000000000022c0000000000000000000000000000022c00000000000002440000000000000f0bf0000000000002440000000000000f0bf000000000000000000000000000022c00000000000000000',
                'expected' => [
                    'type' => 'GEOMETRYCOLLECTION',
                    'srid' => null,
                    'value' => [
                        [
                            'type' => 'POINT',
                            'value' => [0, 1],
                        ],
                        [
                            'type' => 'LINESTRING',
                            'value' => [
                                [2, 3],
                                [4, 5],
                            ],
                        ],
                        [
                            'type' => 'POLYGON',
                            'value' => [
                                [
                                    [0, 0],
                                    [0, 10],
                                    [10, 10],
                                    [10, 0],
                                    [0, 0],
                                ],
                                [
                                    [1, 1],
                                    [1, 9],
                                    [9, 9],
                                    [9, 1],
                                    [1, 1],
                                ],
                            ],
                        ],
                        [
                            'type' => 'MULTIPOINT',
                            'value' => [
                                [0, 1],
                                [2, 3],
                            ],
                        ],
                        [
                            'type' => 'MULTILINESTRING',
                            'value' => [
                                [
                                    [0, 1],
                                    [2, 3],
                                ],
                                [
                                    [4, 5],
                                    [6, 7],
                                ],
                            ],
                        ],
                        [
                            'type' => 'MULTIPOLYGON',
                            'value' => [
                                [
                                    [
                                        [0, 0],
                                        [0, 10],
                                        [10, 10],
                                        [10, 0],
                                        [0, 0],
                                    ],
                                    [
                                        [1, 1],
                                        [1, 9],
                                        [9, 9],
                                        [9, 1],
                                        [1, 1]],
                                ],
                                [
                                    [
                                        [-9, 0],
                                        [-9, 10],
                                        [-1, 10],
                                        [-1, 0],
                                        [-9, 0],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'dimension' => null,
                ],
            ],
            'xdrMultiPointValue2' => [
                'value' => '01040000000200000001010000000000000000000000000000000000f03f010100000000000000000000400000000000000840',
                'expected' => [
                    'type' => 'MULTIPOINT',
                    'value' => [[0, 1], [2, 3]],
                    'srid' => null,
                    'dimension' => null,
                ],
            ],
            'xdrMultiLineStringValue2' => [
                'value' => '0105000000020000000102000000020000000000000000000000000000000000f03f000000000000004000000000000008400102000000020000000000000000001040000000000000144000000000000018400000000000001c40',
                'expected' => [
                    'type' => 'MULTILINESTRING',
                    'value' => [[[0, 1], [2, 3]], [[4, 5], [6, 7]]],
                    'srid' => null,
                    'dimension' => null,
                ],
            ],
            'xdrMultiPolygonValue2' => [
                'value' => '01060000000200000001030000000200000005000000000000000000000000000000000000000000000000000000000000000000244000000000000024400000000000002440000000000000244000000000000000000000000000000000000000000000000005000000000000000000f03f000000000000f03f000000000000f03f0000000000002240000000000000224000000000000022400000000000002240000000000000f03f000000000000f03f000000000000f03f0103000000010000000500000000000000000022c0000000000000000000000000000022c00000000000002440000000000000f0bf0000000000002440000000000000f0bf000000000000000000000000000022c00000000000000000',
                'expected' => [
                    'type' => 'MULTIPOLYGON',
                    'value' => [[[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]], [[1, 1], [1, 9], [9, 9], [9, 1], [1, 1]]], [[[-9, 0], [-9, 10], [-1, 10], [-1, 0], [-9, 0]]]],
                    'srid' => null,
                    'dimension' => null,
                ],
            ],
            'xdrMultiPointZOGCValue' => [
                'value' => '01ec0300000200000001e90300000000000000000000000000000000f03f000000000000004001e9030000000000000000084000000000000010400000000000001440',
                'expected' => [
                    'type' => 'MULTIPOINT',
                    'value' => [[0, 1, 2], [3, 4, 5]],
                    'srid' => null,
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiLineStringZOGCValue' => [
                'value' => '01ed0300000200000001ea030000020000000000000000000000000000000000f03f000000000000004000000000000008400000000000001040000000000000144001ea0300000200000000000000000018400000000000001c400000000000002040000000000000224000000000000024400000000000002640',
                'expected' => [
                    'type' => 'MULTILINESTRING',
                    'value' => [[[0, 1, 2], [3, 4, 5]], [[6, 7, 8], [9, 10, 11]]],
                    'srid' => null,
                    'dimension' => 'Z',
                ],
            ],
            'xdrMultiPolygonZOGCValue' => [
                'value' => '01ee0300000200000001eb030000020000000500000000000000000000000000000000000000000000000000594000000000000000000000000000002440000000000000594000000000000024400000000000002440000000000000594000000000000024400000000000000000000000000000594000000000000000000000000000000000000000000000594005000000000000000000f03f000000000000f03f0000000000005940000000000000f03f000000000000224000000000000059400000000000002240000000000000224000000000000059400000000000002240000000000000f03f0000000000005940000000000000f03f000000000000f03f000000000000594001eb030000010000000500000000000000000022c00000000000000000000000000000494000000000000022c000000000000024400000000000004940000000000000f0bf00000000000024400000000000004940000000000000f0bf0000000000000000000000000000494000000000000022c000000000000000000000000000004940',
                'expected' => [
                    'type' => 'MULTIPOLYGON',
                    'value' => [
                        [
                            [[0, 0, 100], [0, 10, 100], [10, 10, 100], [10, 0, 100], [0, 0, 100]],
                            [[1, 1, 100], [1, 9, 100], [9, 9, 100], [9, 1, 100], [1, 1, 100]],
                        ],
                        [
                            [[-9, 0, 50], [-9, 10, 50], [-1, 10, 50], [-1, 0, 50], [-9, 0, 50]],
                        ],
                    ],
                    'srid' => null,
                    'dimension' => 'Z',
                ],
            ],
            'xdrPointValue2' => [
                'value' => '0101000000000000000000f03f0000000000000040',
                'expected' => [
                    'type' => 'POINT',
                    'value' => [1, 2],
                    'srid' => null,
                    'dimension' => null,
                ],
            ],
            'xdrLineStringValue2' => [
                'value' => '010200000002000000000000000000f03f000000000000004000000000000008400000000000001040',
                'expected' => [
                    'type' => 'LINESTRING',
                    'value' => [[1, 2], [3, 4]],
                    'srid' => null,
                    'dimension' => null,
                ],
            ],
            'xdrPolygonValue2' => [
                'value' => '01030000000200000005000000000000000000000000000000000000000000000000000000000000000000244000000000000024400000000000002440000000000000244000000000000000000000000000000000000000000000000005000000000000000000f03f000000000000f03f000000000000f03f0000000000002240000000000000224000000000000022400000000000002240000000000000f03f000000000000f03f000000000000f03f',
                'expected' => [
                    'type' => 'POLYGON',
                    'value' => [
                        [[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]],
                        [[1, 1], [1, 9], [9, 9], [9, 1], [1, 1]],
                    ],
                    'srid' => null,
                    'dimension' => null,
                ],
            ],
            'xdrPointZOGCValue2' => [
                'value' => '01e9030000000000000000f03f00000000000000400000000000000840',
                'expected' => [
                    'type' => 'POINT',
                    'value' => [1, 2, 3],
                    'srid' => null,
                    'dimension' => 'Z',
                ],
            ],
            'xdrLineStringZOGCValue' => [
                'value' => '01ea03000002000000000000000000f03f00000000000000400000000000000840000000000000104000000000000014400000000000001840',
                'expected' => [
                    'type' => 'LINESTRING',
                    'value' => [[1, 2, 3], [4, 5, 6]],
                    'srid' => null,
                    'dimension' => 'Z',
                ],
            ],
            'xdrPolygonZOGCValue' => [
                'value' => '01eb030000020000000500000000000000000000000000000000000000000000000000594000000000000000000000000000002440000000000000594000000000000024400000000000002440000000000000594000000000000024400000000000000000000000000000594000000000000000000000000000000000000000000000594005000000000000000000f03f000000000000f03f0000000000005940000000000000f03f000000000000224000000000000059400000000000002240000000000000224000000000000059400000000000002240000000000000f03f0000000000005940000000000000f03f000000000000f03f0000000000005940',
                'expected' => [
                    'type' => 'POLYGON',
                    'value' => [
                        [[0, 0, 100], [0, 10, 100], [10, 10, 100], [10, 0, 100], [0, 0, 100]],
                        [[1, 1, 100], [1, 9, 100], [9, 9, 100], [9, 1, 100], [1, 1, 100]],
                    ],
                    'srid' => null,
                    'dimension' => 'Z',
                ],
            ],
        ];
    }

    /**
     * @see https://github.com/postgis/postgis/blob/9eefe39f1c33c7e294ff181183e84500beef9bcf/doc/ZMSgeoms.txt#L70
     *
     * @return \Generator<string, array{int, int}, null, void>
     */
    public static function wkbGeometryType(): \Generator
    {
        yield 'wkbPoint' => [1, Parser::WKB_TYPE_POINT];
        yield 'wkbLineString' => [2, Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygon' => [3, Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPoint' => [4, Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineString' => [5, Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygon' => [6, Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollection' => [7, Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x80000000
        yield 'wkbPointZ' => [0x80000001, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringZ' => [0x80000002, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonZ' => [0x80000003, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointZ' => [0x80000004, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringZ' => [0x80000005, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonZ' => [0x80000006, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionZ' => [0x80000007, Parser::WKB_FLAG_Z | Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x40000000
        yield 'wkbPointM' => [0x40000001, Parser::WKB_FLAG_M | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringM' => [0x40000002, Parser::WKB_FLAG_M | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonM' => [0x40000003, Parser::WKB_FLAG_M | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointM' => [0x40000004, Parser::WKB_FLAG_M | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringM' => [0x40000005, Parser::WKB_FLAG_M | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonM' => [0x40000006, Parser::WKB_FLAG_M | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionM' => [0x40000007, Parser::WKB_FLAG_M | Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x40000000 | 0x80000000
        yield 'wkbPointZM' => [0xC0000001, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringZM' => [0xC0000002, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonZM' => [0xC0000003, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointZM' => [0xC0000004, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringZM' => [0xC0000005, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonZM' => [0xC0000006, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionZM' => [0xC0000007, Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x20000000
        yield 'wkbPointSRID' => [0x20000001, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringSRID' => [0x20000002, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonSRID' => [0x20000003, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointSRID' => [0x20000004, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringSRID' => [0x20000005, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonSRID' => [0x20000006, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionSRID' => [0x20000007, Parser::WKB_FLAG_SRID | Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x20000000 | 0x80000000
        yield 'wkbPointSRIDZ' => [0xA0000001, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringSRIDZ' => [0xA0000002, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonSRIDZ' => [0xA0000003, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointSRIDZ' => [0xA0000004, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringSRIDZ' => [0xA0000005, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonSRIDZ' => [0xA0000006, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionSRIDZ' => [0xA0000007, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x20000000 | 0x40000000
        yield 'wkbPointSRIDM' => [0x60000001, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringSRIDM' => [0x60000002, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonSRIDM' => [0x60000003, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointSRIDM' => [0x60000004, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringSRIDM' => [0x60000005, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonSRIDM' => [0x60000006, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionSRIDM' => [0x60000007, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_TYPE_GEOMETRYCOLLECTION];

        // | 0x20000000 | 0x40000000 | 0x80000000
        yield 'wkbPointSRIDZM' => [0xE0000001, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POINT];
        yield 'wkbLineStringSRIDZM' => [0xE0000002, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_LINESTRING];
        yield 'wkbPolygonSRIDZM' => [0xE0000003, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_POLYGON];
        yield 'wkbMultiPointSRIDZM' => [0xE0000004, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOINT];
        yield 'wkbMultiLineStringSRIDZM' => [0xE0000005, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTILINESTRING];
        yield 'wkbMultiPolygonSRIDZM' => [0xE0000006, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_MULTIPOLYGON];
        yield 'wkbGeometryCollectionSRIDZM' => [0xE0000007, Parser::WKB_FLAG_SRID | Parser::WKB_FLAG_M | Parser::WKB_FLAG_Z | Parser::WKB_TYPE_GEOMETRYCOLLECTION];
    }

    /**
     * @param string $exception
     * @param string $message
     *
     * @dataProvider badBinaryData
     */
    public function testBadBinaryData($value, $exception, $message)
    {
        self::expectException($exception);

        if ('/' === $message[0]) {
            self::expectExceptionMessageMatches($message);
        } else {
            self::expectExceptionMessage($message);
        }

        $parser = new Parser($value);

        $parser->parse();
    }

    #[DataProvider('wkbGeometryType')]
    public function testGeometryType(int $expected, int $actual): void
    {
        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider goodBinaryData
     */
    public function testParserBinary($value, array $expected)
    {
        $parser = new Parser(pack('H*', $value));
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider goodBinaryData
     */
    public function testParserPrependLower0XHex($value, array $expected)
    {
        $parser = new Parser('0x'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider goodBinaryData
     */
    public function testParserPrependLowerXHex($value, array $expected)
    {
        $parser = new Parser('x'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider goodBinaryData
     */
    public function testParserPrependUpper0XHex($value, array $expected)
    {
        $parser = new Parser('0X'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider goodBinaryData
     */
    public function testParserPrependUpperXHex($value, array $expected)
    {
        $parser = new Parser('X'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider goodBinaryData
     */
    public function testParserRawHex($value, array $expected)
    {
        $parser = new Parser($value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    public function testReusedParser()
    {
        $parser = new Parser();

        foreach ($this->goodBinaryData() as $testData) {
            $actual = $parser->parse($testData['value']);

            $this->assertEquals($testData['expected'], $actual);

            $actual = $parser->parse('x'.$testData['value']);

            $this->assertEquals($testData['expected'], $actual);

            $actual = $parser->parse('X'.$testData['value']);

            $this->assertEquals($testData['expected'], $actual);

            $actual = $parser->parse('0x'.$testData['value']);

            $this->assertEquals($testData['expected'], $actual);

            $actual = $parser->parse('0X'.$testData['value']);

            $this->assertEquals($testData['expected'], $actual);

            $actual = $parser->parse(pack('H*', $testData['value']));

            $this->assertEquals($testData['expected'], $actual);
        }
    }
}
