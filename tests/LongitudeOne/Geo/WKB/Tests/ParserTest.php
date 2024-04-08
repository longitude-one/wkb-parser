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
     * @return \Generator<string, array{value:string, exception:class-string<ExceptionInterface>, message:string}, null, void>
     */
    public static function badBinaryData(): \Generator
    {
        yield 'badByteOrder' => [
            'value' => pack('H*', '03010000003D0AD7A3701D41400000000000C055C0'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Invalid byte order "3" at byte 0',
        ];
        yield 'badSimpleType' => [
            'value' => pack('H*', '01150000003D0AD7A3701D41400000000000C055C0'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Unsupported WKB type "21" (0x15) at byte 1',
        ];

        // Short NDR POINT
        $message = 'Type d: not enough input values, need 8 values but only 4 were provided';
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $message = 'LongitudeOne\Geo\WKB\Reader: Error number 2: unpack(): Type d: not enough input, need 8, have 4. at byte 5';
        }
        yield 'shortNDRPoint' => [
            'value' => pack('H*', '01010000003D0AD7A3701D414000000000'),
            'exception' => InvalidArgumentException::class,
            'message' => $message,
        ];

        yield 'badPointSize' => [
            'value' => pack('H*', '0000000FA1'),
            'exception' => UnexpectedValueException::class,
            'message' => 'POINT with unsupported dimensions 0xFA0 (4000) at byte 1',
        ];
        yield 'badPointInMultiPoint' => [
            'value' => pack('H*', '0080000004000000020000000001'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Bad POINT with dimensions 0x0 (0) in MULTIPOINT, expected dimensions 0x80000000 (2147483648) at byte 10',
        ];
        yield 'unexpectedLineStringInMultiPoint' => [
            'value' => pack('H*', '0080000004000000020000000002'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Unexpected LINESTRING with dimensions 0x0 (0) in MULTIPOINT, expected POINT with dimensions 0x80000000 (2147483648) at byte 10',
        ];
        yield 'badLineStringInMultiLineString' => [
            'value' => pack('H*', '0000000005000000020080000002'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Bad LINESTRING with dimensions 0x80000000 (2147483648) in MULTILINESTRING, expected dimensions 0x0 (0) at byte 10',
        ];
        yield 'badPolygonInMultiPolygon' => [
            'value' => pack('H*', '0080000006000000020000000003'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Bad POLYGON with dimensions 0x0 (0) in MULTIPOLYGON, expected dimensions 0x80000000 (2147483648) at byte 10',
        ];
        yield 'badCircularStringInCompoundCurve' => [
            'value' => pack('H*', '0080000009000000020000000008'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Bad CIRCULARSTRING with dimensions 0x0 (0) in COMPOUNDCURVE, expected dimensions 0x80000000 (2147483648) at byte 10',
        ];
        yield 'unexpectedPointInCompoundCurve' => [
            'value' => pack('H*', '0080000009000000020000000001'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Unexpected POINT with dimensions 0x0 (0) in COMPOUNDCURVE, expected LINESTRING or CIRCULARSTRING with dimensions 0x80000000 (2147483648) at byte 10',
        ];
        yield 'badCompoundCurveInCurvePolygon' => [
            'value' => pack('H*', '000000000a000000010080000009'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Bad COMPOUNDCURVE with dimensions 0x80000000 (2147483648) in CURVEPOLYGON, expected dimensions 0x0 (0) at byte 10',
        ];
        yield 'badCircularStringInCurvePolygon' => [
            'value' => pack('H*', '008000000a000000010080000009000000020000000008'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Bad CIRCULARSTRING with dimensions 0x0 (0) in CURVEPOLYGON, expected dimensions 0x80000000 (2147483648) at byte 19',
        ];
        yield 'unexpectedPolygonInMultiCurve' => [
            'value' => pack('H*', '004000000b000000010040000003'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Unexpected POLYGON with dimensions 0x40000000 (1073741824) in MULTICURVE, expected LINESTRING, CIRCULARSTRING or COMPOUNDCURVE with dimensions 0x40000000 (1073741824) at byte 10',
        ];
        yield 'unexpectedPointInMultiSurface' => [
            'value' => pack('H*', '008000000c000000020080000001'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Unexpected POINT with dimensions 0x80000000 (2147483648) in MULTISURFACE, expected POLYGON or CURVEPOLYGON with dimensions 0x80000000 (2147483648) at byte 10',
        ];
        yield 'unexpectedPointInPolyhedralSurface' => [
            'value' => pack('H*', '010f000080050000000101000080'),
            'exception' => UnexpectedValueException::class,
            'message' => 'Unexpected POINT with dimensions 0x80000000 (2147483648) in POLYHEDRALSURFACE, expected POLYGON with dimensions 0x80000000 (2147483648) at byte 10',
        ];
    }

    /**
     * @return \Generator<string, array{value:string, expected: array{srid: ?int, type: string, value: (float|int|(float|int)[]|(float|int)[][]|(float|int)[][][]|array{type:string, value:(float|int|array{type:string, value:(float|int|(float|int)[]|array{type:string, value: int[][]|int[][][]})[]})[]})[], dimension: ?string}}, null, void>
     */
    public static function goodBinaryData(): \Generator
    {
        yield 'ndrEmptyPointValue' => [
            'value' => '0101000000000000000000F87F000000000000F87F',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [],
                'dimension' => null,
            ],
        ];
        yield 'ndrPointValue' => [
            'value' => '01010000003D0AD7A3701D41400000000000C055C0',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [34.23, -87],
                'dimension' => null,
            ],
        ];
        yield 'xdrPointValue' => [
            'value' => '000000000140411D70A3D70A3DC055C00000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [34.23, -87],
                'dimension' => null,
            ],
        ];
        yield 'ndrPointZValue' => [
            'value' => '0101000080000000000000F03F00000000000000400000000000000840',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrPointZValue' => [
            'value' => '00800000013FF000000000000040000000000000004008000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrPointZOGCValue' => [
            'value' => '00000003E94117C89F84189375411014361BA5E3540000000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [389671.879, 263437.527, 0],
                'dimension' => 'Z',
            ],
        ];
        yield 'ndrPointMValue' => [
            'value' => '0101000040000000000000F03F00000000000000400000000000000840',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'M',
            ],
        ];
        yield 'xdrPointMValue' => [
            'value' => '00400000013FF000000000000040000000000000004008000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'M',
            ],
        ];
        yield 'ndrEmptyPointZMValue' => [
            'value' => '01010000C0000000000000F87F000000000000F87F000000000000F87F000000000000F87F',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrEmptyPointZMValue' => [
            'value' => '00C00000017FF80000000000007FF80000000000007FF80000000000007FF8000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrPointZMValue' => [
            'value' => '01010000C0000000000000F03F000000000000004000000000000008400000000000001040',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [1, 2, 3, 4],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrPointZMValue' => [
            'value' => '00C00000013FF0000000000000400000000000000040080000000000004010000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [1, 2, 3, 4],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrPointValueWithSrid' => [
            'value' => '01010000003D0AD7A3701D41400000000000C055C0',
            'expected' => [
                'srid' => null,
                'type' => 'POINT',
                'value' => [34.23, -87],
                'dimension' => null,
            ],
        ];
        yield 'xdrPointValueWithSrid' => [
            'value' => '0020000001000010E640411D70A3D70A3DC055C00000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [34.23, -87],
                'dimension' => null,
            ],
        ];
        yield 'ndrPointZValueWithSrid' => [
            'value' => '01010000A0E6100000000000000000F03F00000000000000400000000000000840',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrPointZValueWithSrid' => [
            'value' => '00A0000001000010E63FF000000000000040000000000000004008000000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'Z',
            ],
        ];
        yield 'ndrPointMValueWithSrid' => [
            'value' => '0101000060e6100000000000000000f03f00000000000000400000000000000840',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'M',
            ],
        ];
        yield 'xdrPointMValueWithSrid' => [
            'value' => '0060000001000010e63ff000000000000040000000000000004008000000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'dimension' => 'M',
            ],
        ];
        yield 'ndrEmptyPointZMValueWithSrid' => [
            'value' => '01010000E08C100000000000000000F87F000000000000F87F000000000000F87F000000000000F87F',
            'expected' => [
                'srid' => 4236,
                'type' => 'POINT',
                'value' => [],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrPointZMValueWithSrid' => [
            'value' => '01010000e0e6100000000000000000f03f000000000000004000000000000008400000000000001040',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [1, 2, 3, 4],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrPointZMValueWithSrid' => [
            'value' => '00e0000001000010e63ff0000000000000400000000000000040080000000000004010000000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'POINT',
                'value' => [1, 2, 3, 4],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrEmptyLineStringValue' => [
            'value' => '010200000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [],
                'dimension' => null,
            ],
        ];
        yield 'ndrLineStringValue' => [
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
        ];
        yield 'xdrLineStringValue' => [
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
        ];

        yield 'ndrLineStringZValue' => [
            'value' => '010200008002000000000000000000000000000000000000000000000000000040000000000000f03f000000000000f03f0000000000000840',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'Z',
            ],
        ];

        yield 'xdrLineStringZValue' => [
            'value' => '0080000002000000020000000000000000000000000000000040000000000000003ff00000000000003ff00000000000004008000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'ndrLineStringMValue' => [
            'value' => '010200004002000000000000000000000000000000000000000000000000000040000000000000f03f000000000000f03f0000000000000840',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'M',
            ],
        ];

        yield 'xdrLineStringMValue' => [
            'value' => '0040000002000000020000000000000000000000000000000040000000000000003ff00000000000003ff00000000000004008000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'M',
            ],
        ];

        yield 'ndrLineStringZMValue' => [
            'value' => '01020000c0020000000000000000000000000000000000000000000000000000400000000000000840000000000000f03f000000000000f03f00000000000010400000000000001440',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2, 3],
                    [1, 1, 4, 5],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrLineStringZMValue' => [
            'value' => '00c00000020000000200000000000000000000000000000000400000000000000040080000000000003ff00000000000003ff000000000000040100000000000004014000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2, 3],
                    [1, 1, 4, 5],
                ],
                'dimension' => 'ZM',
            ],
        ];

        yield 'ndrLineStringValueWithSrid' => [
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
        ];
        yield 'xdrLineStringValueWithSrid' => [
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
        ];
        yield 'ndrLineStringZValueWithSrid' => [
            'value' => '01020000a0e610000002000000000000000000000000000000000000000000000000000040000000000000f03f000000000000f03f0000000000000840',
            'expected' => [
                'srid' => 4326,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrLineStringZValueWithSrid' => [
            'value' => '00a0000002000010e6000000020000000000000000000000000000000040000000000000003ff00000000000003ff00000000000004008000000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'ndrLineStringMValueWithSrid' => [
            'value' => '0102000060e610000002000000000000000000000000000000000000000000000000000040000000000000f03f000000000000f03f0000000000000840',
            'expected' => [
                'srid' => 4326,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'M',
            ],
        ];
        yield 'xdrLineStringMValueWithSrid' => [
            'value' => '0060000002000010e6000000020000000000000000000000000000000040000000000000003ff00000000000003ff00000000000004008000000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2],
                    [1, 1, 3],
                ],
                'dimension' => 'M',
            ],
        ];
        yield 'ndrLineStringZMValueWithSrid' => [
            'value' => '01020000e0e6100000020000000000000000000000000000000000000000000000000000400000000000000840000000000000f03f000000000000f03f00000000000010400000000000001440',
            'expected' => [
                'srid' => 4326,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2, 3],
                    [1, 1, 4, 5],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrLineStringZMValueWithSrid' => [
            'value' => '00e0000002000010e60000000200000000000000000000000000000000400000000000000040080000000000003ff00000000000003ff000000000000040100000000000004014000000000000',
            'expected' => [
                'srid' => 4326,
                'type' => 'LINESTRING',
                'value' => [
                    [0, 0, 2, 3],
                    [1, 1, 4, 5],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrEmptyPolygonValue' => [
            'value' => '010300000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'POLYGON',
                'value' => [],
                'dimension' => null,
            ],
        ];
        yield 'ndrPolygonValue' => [
            'value' => '010300000001000000050000000000000000000000000000000000000000000000000024400000000000000000000000000000244000000000000024400000000000000000000000000000244000000000000000000000000000000000',
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
        ];
        yield 'xdrPolygonValue' => [
            'value' => '000000000300000001000000050000000000000000000000000000000040240000000000000000000000000000402400000000000040240000000000000000000000000000402400000000000000000000000000000000000000000000',
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
        ];
        yield 'ndrPolygonValueWithSrid' => [
            'value' => '0103000020E610000001000000050000000000000000000000000000000000000000000000000024400000000000000000000000000000244000000000000024400000000000000000000000000000244000000000000000000000000000000000',
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
        ];
        yield 'xdrPolygonValueWithSrid' => [
            'value' => '0020000003000010E600000001000000050000000000000000000000000000000040240000000000000000000000000000402400000000000040240000000000000000000000000000402400000000000000000000000000000000000000000000',
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
        ];
        yield 'ndrMultiRingPolygonValue' => [
            'value' => '01030000000200000005000000000000000000000000000000000000000000000000002440000000000000000000000000000024400000000000002440000000000000000000000000000024400000000000000000000000000000000005000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000000000014400000000000001C4000000000000014400000000000001440',
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
        ];
        yield 'xdrMultiRingPolygonValue' => [
            'value' => '0000000003000000020000000500000000000000000000000000000000402400000000000000000000000000004024000000000000402400000000000000000000000000004024000000000000000000000000000000000000000000000000000540140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C0000000000004014000000000000401C00000000000040140000000000004014000000000000',
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
        ];
        yield 'ndrMultiRingPolygonZValue' => [
            'value' => '0103000080020000000500000000000000000000000000000000000000000000000000f03f00000000000024400000000000000000000000000000004000000000000024400000000000002440000000000000004000000000000000000000000000002440000000000000004000000000000000000000000000000000000000000000f03f05000000000000000000004000000000000000400000000000001440000000000000004000000000000014400000000000001040000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000001440',
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
        ];
        yield 'xdrMultiRingPolygonZValue' => [
            'value' => '00800000030000000200000005000000000000000000000000000000003ff0000000000000402400000000000000000000000000004000000000000000402400000000000040240000000000004000000000000000000000000000000040240000000000004000000000000000000000000000000000000000000000003ff000000000000000000005400000000000000040000000000000004014000000000000400000000000000040140000000000004010000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004014000000000000',
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
        ];
        yield 'ndrMultiRingPolygonMValue' => [
            'value' => '0103000040020000000500000000000000000000000000000000000000000000000000f03f00000000000024400000000000000000000000000000004000000000000024400000000000002440000000000000004000000000000000000000000000002440000000000000004000000000000000000000000000000000000000000000f03f05000000000000000000004000000000000000400000000000001440000000000000004000000000000014400000000000001040000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000001440',
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
        ];
        yield 'xdrMultiRingPolygonMValue' => [
            'value' => '00400000030000000200000005000000000000000000000000000000003ff0000000000000402400000000000000000000000000004000000000000000402400000000000040240000000000004000000000000000000000000000000040240000000000004000000000000000000000000000000000000000000000003ff000000000000000000005400000000000000040000000000000004014000000000000400000000000000040140000000000004010000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004014000000000000',
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
        ];
        yield 'ndrMultiRingPolygonZMValue' => [
            'value' => '01030000c0020000000500000000000000000000000000000000000000000000000000f03f000000000000f0bf00000000000024400000000000000000000000000000004000000000000000c000000000000024400000000000002440000000000000004000000000000000c000000000000000000000000000002440000000000000004000000000000010c000000000000000000000000000000000000000000000f03f000000000000f0bf050000000000000000000040000000000000004000000000000014400000000000000000000000000000004000000000000014400000000000001040000000000000f03f0000000000001440000000000000144000000000000008400000000000000040000000000000144000000000000000400000000000000840000000000000f03f0000000000000040000000000000004000000000000014400000000000000000',
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
        ];
        yield 'xdrMultiRingPolygonZMValue' => [
            'value' => '00c00000030000000200000005000000000000000000000000000000003ff0000000000000bff0000000000000402400000000000000000000000000004000000000000000c000000000000000402400000000000040240000000000004000000000000000c000000000000000000000000000000040240000000000004000000000000000c010000000000000000000000000000000000000000000003ff0000000000000bff00000000000000000000540000000000000004000000000000000401400000000000000000000000000004000000000000000401400000000000040100000000000003ff000000000000040140000000000004014000000000000400800000000000040000000000000004014000000000000400000000000000040080000000000003ff00000000000004000000000000000400000000000000040140000000000000000000000000000',
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
        ];
        yield 'ndrMultiRingPolygonValueWithSrid' => [
            'value' => '0103000020E61000000200000005000000000000000000000000000000000000000000000000002440000000000000000000000000000024400000000000002440000000000000000000000000000024400000000000000000000000000000000005000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000000000014400000000000001C4000000000000014400000000000001440',
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
        ];
        yield 'xdrMultiRingPolygonValueWithSrid' => [
            'value' => '0020000003000010E6000000020000000500000000000000000000000000000000402400000000000000000000000000004024000000000000402400000000000000000000000000004024000000000000000000000000000000000000000000000000000540140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C0000000000004014000000000000401C00000000000040140000000000004014000000000000',
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
        ];
        yield 'ndrMultiRingPolygonZValueWithSrid' => [
            'value' => '01030000a0e6100000020000000500000000000000000000000000000000000000000000000000f03f00000000000024400000000000000000000000000000004000000000000024400000000000002440000000000000004000000000000000000000000000002440000000000000004000000000000000000000000000000000000000000000f03f05000000000000000000004000000000000000400000000000001440000000000000004000000000000014400000000000001040000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000001440',
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
        ];
        yield 'xdrMultiRingPolygonZValueWithSrid' => [
            'value' => '00a0000003000010e60000000200000005000000000000000000000000000000003ff0000000000000402400000000000000000000000000004000000000000000402400000000000040240000000000004000000000000000000000000000000040240000000000004000000000000000000000000000000000000000000000003ff000000000000000000005400000000000000040000000000000004014000000000000400000000000000040140000000000004010000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004014000000000000',
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
        ];
        yield 'ndrMultiRingPolygonMValueWithSrid' => [
            'value' => '0103000060e6100000020000000500000000000000000000000000000000000000000000000000f03f00000000000024400000000000000000000000000000004000000000000024400000000000002440000000000000004000000000000000000000000000002440000000000000004000000000000000000000000000000000000000000000f03f05000000000000000000004000000000000000400000000000001440000000000000004000000000000014400000000000001040000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000001440',
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
        ];
        yield 'xdrMultiRingPolygonMValueWithSrid' => [
            'value' => '0060000003000010e60000000200000005000000000000000000000000000000003ff0000000000000402400000000000000000000000000004000000000000000402400000000000040240000000000004000000000000000000000000000000040240000000000004000000000000000000000000000000000000000000000003ff000000000000000000005400000000000000040000000000000004014000000000000400000000000000040140000000000004010000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004014000000000000',
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
        ];
        yield 'ndrMultiRingPolygonZMValueWithSrid' => [
            'value' => '01030000e0e6100000020000000500000000000000000000000000000000000000000000000000f03f000000000000f0bf00000000000024400000000000000000000000000000004000000000000000c000000000000024400000000000002440000000000000004000000000000000c000000000000000000000000000002440000000000000004000000000000010c000000000000000000000000000000000000000000000f03f000000000000f0bf050000000000000000000040000000000000004000000000000014400000000000000000000000000000004000000000000014400000000000001040000000000000f03f0000000000001440000000000000144000000000000008400000000000000040000000000000144000000000000000400000000000000840000000000000f03f0000000000000040000000000000004000000000000014400000000000000000',
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
        ];
        yield 'xdrMultiRingPolygonZMValueWithSrid' => [
            'value' => '00e0000003000010e60000000200000005000000000000000000000000000000003ff0000000000000bff0000000000000402400000000000000000000000000004000000000000000c000000000000000402400000000000040240000000000004000000000000000c000000000000000000000000000000040240000000000004000000000000000c010000000000000000000000000000000000000000000003ff0000000000000bff00000000000000000000540000000000000004000000000000000401400000000000000000000000000004000000000000000401400000000000040100000000000003ff000000000000040140000000000004014000000000000400800000000000040000000000000004014000000000000400000000000000040080000000000003ff00000000000004000000000000000400000000000000040140000000000000000000000000000',
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
        ];
        yield 'ndrMultiPointValue' => [
            'value' => '010400000004000000010100000000000000000000000000000000000000010100000000000000000024400000000000000000010100000000000000000024400000000000002440010100000000000000000000000000000000002440',
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
        ];
        yield 'xdrMultiPointValue' => [
            'value' => '000000000400000004000000000100000000000000000000000000000000000000000140240000000000000000000000000000000000000140240000000000004024000000000000000000000100000000000000004024000000000000',
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
        ];
        yield 'ndrMultiPointZValue' => [
            'value' => '0104000080020000000101000080000000000000000000000000000000000000000000000000010100008000000000000000400000000000000000000000000000f03f',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 0],
                    [2, 0, 1],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrMultiPointZValue' => [
            'value' => '00800000040000000200800000010000000000000000000000000000000000000000000000000080000001400000000000000000000000000000003ff0000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 0],
                    [2, 0, 1],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'ndrMultiPointMValue' => [
            'value' => '0104000040020000000101000040000000000000000000000000000000000000000000000040010100004000000000000000400000000000000000000000000000f03f',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 2],
                    [2, 0, 1],
                ],
                'dimension' => 'M',
            ],
        ];
        yield 'xdrMultiPointMValue' => [
            'value' => '00400000040000000200400000010000000000000000000000000000000040000000000000000040000001400000000000000000000000000000003ff0000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 2],
                    [2, 0, 1],
                ],
                'dimension' => 'M',
            ],
        ];
        yield 'ndrMultiPointZMValue' => [
            'value' => '01040000c00200000001010000c00000000000000000000000000000f03f0000000000000040000000000000084001010000c000000000000008400000000000000040000000000000f03f0000000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 1, 2, 3],
                    [3, 2, 1, 0],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrMultiPointZMValue' => [
            'value' => '00c00000040000000200c000000100000000000000003ff00000000000004000000000000000400800000000000000c0000001400800000000000040000000000000003ff00000000000000000000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 1, 2, 3],
                    [3, 2, 1, 0],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrMultiPointValueWithSrid' => [
            'value' => '0104000020E610000004000000010100000000000000000000000000000000000000010100000000000000000024400000000000000000010100000000000000000024400000000000002440010100000000000000000000000000000000002440',
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
        ];
        yield 'xdrMultiPointValueWithSrid' => [
            'value' => '0020000004000010E600000004000000000100000000000000000000000000000000000000000140240000000000000000000000000000000000000140240000000000004024000000000000000000000100000000000000004024000000000000',
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
        ];
        yield 'ndrMultiPointZValueWithSrid' => [
            'value' => '0104000080020000000101000080000000000000000000000000000000000000000000000000010100008000000000000000400000000000000000000000000000f03f',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 0],
                    [2, 0, 1],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrMultiPointZValueWithSrid' => [
            'value' => '00800000040000000200800000010000000000000000000000000000000000000000000000000080000001400000000000000000000000000000003ff0000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 0],
                    [2, 0, 1],
                ],
                'dimension' => 'Z',
            ],
        ];
        yield 'ndrMultiPointMValueWithSrid' => [
            'value' => '0104000040020000000101000040000000000000000000000000000000000000000000000040010100004000000000000000400000000000000000000000000000f03f',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 2],
                    [2, 0, 1],
                ],
                'dimension' => 'M',
            ],
        ];
        yield 'xdrMultiPointMValueWithSrid' => [
            'value' => '00400000040000000200400000010000000000000000000000000000000040000000000000000040000001400000000000000000000000000000003ff0000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 0, 2],
                    [2, 0, 1],
                ],
                'dimension' => 'M',
            ],
        ];
        yield 'ndrMultiPointZMValueWithSrid' => [
            'value' => '01040000c00200000001010000c00000000000000000000000000000f03f0000000000000040000000000000084001010000c000000000000008400000000000000040000000000000f03f0000000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 1, 2, 3],
                    [3, 2, 1, 0],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'xdrMultiPointZMValueWithSrid' => [
            'value' => '00c00000040000000200c000000100000000000000003ff00000000000004000000000000000400800000000000000c0000001400800000000000040000000000000003ff00000000000000000000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'MULTIPOINT',
                'value' => [
                    [0, 1, 2, 3],
                    [3, 2, 1, 0],
                ],
                'dimension' => 'ZM',
            ],
        ];
        yield 'ndrMultiLineStringValue' => [
            'value' => '01050000000200000001020000000400000000000000000000000000000000000000000000000000244000000000000000000000000000002440000000000000244000000000000000000000000000002440010200000004000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000000000014400000000000001C40',
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
        ];
        yield 'xdrMultiLineStringValue' => [
            'value' => '0000000005000000020000000002000000040000000000000000000000000000000040240000000000000000000000000000402400000000000040240000000000000000000000000000402400000000000000000000020000000440140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C0000000000004014000000000000401C000000000000',
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
        ];
        yield 'ndrMultiLineStringZValue' => [
            'value' => '01050000800200000001020000800200000000000000000000000000000000000000000000000000f03f000000000000004000000000000000000000000000000040010200008002000000000000000000f03f000000000000f03f0000000000000840000000000000004000000000000000400000000000001040',
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
        ];
        yield 'xdrMultiLineStringZValue' => [
            'value' => '008000000500000002008000000200000002000000000000000000000000000000003ff00000000000004000000000000000000000000000000040000000000000000080000002000000023ff00000000000003ff00000000000004008000000000000400000000000000040000000000000004010000000000000',
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
        ];
        yield 'ndrMultiLineStringMValue' => [
            'value' => '01050000400200000001020000400200000000000000000000000000000000000000000000000000f03f000000000000004000000000000000000000000000000040010200004002000000000000000000f03f000000000000f03f0000000000000840000000000000004000000000000000400000000000001040',
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
        ];
        yield 'xdrMultiLineStringMValue' => [
            'value' => '004000000500000002004000000200000002000000000000000000000000000000003ff00000000000004000000000000000000000000000000040000000000000000040000002000000023ff00000000000003ff00000000000004008000000000000400000000000000040000000000000004010000000000000',
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
        ];
        yield 'ndrMultiLineStringZMValue' => [
            'value' => '01050000c00200000001020000c00200000000000000000000000000000000000000000000000000f03f0000000000001440000000000000004000000000000000000000000000000040000000000000104001020000c002000000000000000000f03f000000000000f03f000000000000084000000000000008400000000000000040000000000000004000000000000010400000000000000040',
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
        ];
        yield 'xdrMultiLineStringZMValue' => [
            'value' => '00c00000050000000200c000000200000002000000000000000000000000000000003ff00000000000004014000000000000400000000000000000000000000000004000000000000000401000000000000000c0000002000000023ff00000000000003ff0000000000000400800000000000040080000000000004000000000000000400000000000000040100000000000004000000000000000',
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
        ];
        yield 'ndrMultiLineStringValueWithSrid' => [
            'value' => '0105000020E61000000200000001020000000400000000000000000000000000000000000000000000000000244000000000000000000000000000002440000000000000244000000000000000000000000000002440010200000004000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000000000014400000000000001C40',
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
        ];
        yield 'xdrMultiLineStringValueWithSrid' => [
            'value' => '0020000005000010E6000000020000000002000000040000000000000000000000000000000040240000000000000000000000000000402400000000000040240000000000000000000000000000402400000000000000000000020000000440140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C0000000000004014000000000000401C000000000000',
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
        ];
        yield 'ndrMultiLineStringZValueWithSrid' => [
            'value' => '01050000a0e61000000200000001020000800200000000000000000000000000000000000000000000000000f03f000000000000004000000000000000000000000000000040010200008002000000000000000000f03f000000000000f03f0000000000000840000000000000004000000000000000400000000000001040',
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
        ];
        yield 'xdrMultiLineStringZValueWithSrid' => [
            'value' => '008000000500000002008000000200000002000000000000000000000000000000003ff00000000000004000000000000000000000000000000040000000000000000080000002000000023ff00000000000003ff00000000000004008000000000000400000000000000040000000000000004010000000000000',
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
        ];
        yield 'ndrMultiLineStringMValueWithSrid' => [
            'value' => '0105000060e61000000200000001020000400200000000000000000000000000000000000000000000000000f03f000000000000004000000000000000000000000000000040010200004002000000000000000000f03f000000000000f03f0000000000000840000000000000004000000000000000400000000000001040',
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
        ];
        yield 'xdrMultiLineStringMValueWithSrid' => [
            'value' => '004000000500000002004000000200000002000000000000000000000000000000003ff00000000000004000000000000000000000000000000040000000000000000040000002000000023ff00000000000003ff00000000000004008000000000000400000000000000040000000000000004010000000000000',
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
        ];
        yield 'ndrMultiLineStringZMValueWithSrid' => [
            'value' => '01050000e0e61000000200000001020000c00200000000000000000000000000000000000000000000000000f03f0000000000001440000000000000004000000000000000000000000000000040000000000000104001020000c002000000000000000000f03f000000000000f03f000000000000084000000000000008400000000000000040000000000000004000000000000010400000000000000040',
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
        ];
        yield 'xdrMultiLineStringZMValueWithSrid' => [
            'value' => '00c00000050000000200c000000200000002000000000000000000000000000000003ff00000000000004014000000000000400000000000000000000000000000004000000000000000401000000000000000c0000002000000023ff00000000000003ff0000000000000400800000000000040080000000000004000000000000000400000000000000040100000000000004000000000000000',
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
        ];
        yield 'ndrMultiPolygonValue' => [
            'value' => '01060000000200000001030000000200000005000000000000000000000000000000000000000000000000002440000000000000000000000000000024400000000000002440000000000000000000000000000024400000000000000000000000000000000005000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000000000014400000000000001C400000000000001440000000000000144001030000000100000005000000000000000000F03F000000000000F03F0000000000000840000000000000F03F00000000000008400000000000000840000000000000F03F0000000000000840000000000000F03F000000000000F03F',
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
        ];
        yield 'xdrMultiPolygonValue' => [
            'value' => '0000000006000000020000000003000000020000000500000000000000000000000000000000402400000000000000000000000000004024000000000000402400000000000000000000000000004024000000000000000000000000000000000000000000000000000540140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C0000000000004014000000000000401C00000000000040140000000000004014000000000000000000000300000001000000053FF00000000000003FF000000000000040080000000000003FF0000000000000400800000000000040080000000000003FF000000000000040080000000000003FF00000000000003FF0000000000000',
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
        ];
        yield 'ndrMultiPolygonZValue' => [
            'value' => '0106000080010000000103000080020000000500000000000000000000000000000000000000000000000000084000000000000024400000000000000000000000000000084000000000000024400000000000002440000000000000084000000000000000000000000000002440000000000000084000000000000000000000000000000000000000000000084005000000000000000000004000000000000000400000000000000840000000000000004000000000000014400000000000000840000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000000840',
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
        ];
        yield 'xdrMultiPolygonZValue' => [
            'value' => '0080000006000000010080000003000000020000000500000000000000000000000000000000400800000000000040240000000000000000000000000000400800000000000040240000000000004024000000000000400800000000000000000000000000004024000000000000400800000000000000000000000000000000000000000000400800000000000000000005400000000000000040000000000000004008000000000000400000000000000040140000000000004008000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004008000000000000',
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
        ];
        yield 'ndrMultiPolygonMValue' => [
            'value' => '0106000040010000000103000040020000000500000000000000000000000000000000000000000000000000084000000000000024400000000000000000000000000000084000000000000024400000000000002440000000000000084000000000000000000000000000002440000000000000084000000000000000000000000000000000000000000000084005000000000000000000004000000000000000400000000000000840000000000000004000000000000014400000000000000840000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000000840',
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
        ];
        yield 'xdrMultiPolygonMValue' => [
            'value' => '0040000006000000010040000003000000020000000500000000000000000000000000000000400800000000000040240000000000000000000000000000400800000000000040240000000000004024000000000000400800000000000000000000000000004024000000000000400800000000000000000000000000000000000000000000400800000000000000000005400000000000000040000000000000004008000000000000400000000000000040140000000000004008000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004008000000000000',
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
        ];
        yield 'ndrMultiPolygonZMValue' => [
            'value' => '01060000c00100000001030000c00200000005000000000000000000000000000000000000000000000000000840000000000000004000000000000024400000000000000000000000000000084000000000000000400000000000002440000000000000244000000000000008400000000000000040000000000000000000000000000024400000000000000840000000000000004000000000000000000000000000000000000000000000084000000000000000400500000000000000000000400000000000000040000000000000084000000000000000400000000000000040000000000000144000000000000008400000000000000040000000000000144000000000000014400000000000000840000000000000004000000000000014400000000000000040000000000000084000000000000000400000000000000040000000000000004000000000000008400000000000000040',
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
        ];
        yield 'xdrMultiPolygonZMValue' => [
            'value' => '00c00000060000000100c00000030000000200000005000000000000000000000000000000004008000000000000400000000000000040240000000000000000000000000000400800000000000040000000000000004024000000000000402400000000000040080000000000004000000000000000000000000000000040240000000000004008000000000000400000000000000000000000000000000000000000000000400800000000000040000000000000000000000540000000000000004000000000000000400800000000000040000000000000004000000000000000401400000000000040080000000000004000000000000000401400000000000040140000000000004008000000000000400000000000000040140000000000004000000000000000400800000000000040000000000000004000000000000000400000000000000040080000000000004000000000000000',
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
        ];
        yield 'ndrMultiPolygonValueWithSrid' => [
            'value' => '0106000020E61000000200000001030000000200000005000000000000000000000000000000000000000000000000002440000000000000000000000000000024400000000000002440000000000000000000000000000024400000000000000000000000000000000005000000000000000000144000000000000014400000000000001C4000000000000014400000000000001C400000000000001C4000000000000014400000000000001C400000000000001440000000000000144001030000000100000005000000000000000000F03F000000000000F03F0000000000000840000000000000F03F00000000000008400000000000000840000000000000F03F0000000000000840000000000000F03F000000000000F03F',
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
        ];
        yield 'xdrMultiPolygonValueWithSrid' => [
            'value' => '0020000006000010E6000000020000000003000000020000000500000000000000000000000000000000402400000000000000000000000000004024000000000000402400000000000000000000000000004024000000000000000000000000000000000000000000000000000540140000000000004014000000000000401C0000000000004014000000000000401C000000000000401C0000000000004014000000000000401C00000000000040140000000000004014000000000000000000000300000001000000053FF00000000000003FF000000000000040080000000000003FF0000000000000400800000000000040080000000000003FF000000000000040080000000000003FF00000000000003FF0000000000000',
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
        ];
        yield 'ndrMultiPolygonZValueWithSrid' => [
            'value' => '01060000a0e6100000010000000103000080020000000500000000000000000000000000000000000000000000000000084000000000000024400000000000000000000000000000084000000000000024400000000000002440000000000000084000000000000000000000000000002440000000000000084000000000000000000000000000000000000000000000084005000000000000000000004000000000000000400000000000000840000000000000004000000000000014400000000000000840000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000000840',
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
        ];
        yield 'xdrMultiPolygonZValueWithSrid' => [
            'value' => '00a0000006000010e6000000010080000003000000020000000500000000000000000000000000000000400800000000000040240000000000000000000000000000400800000000000040240000000000004024000000000000400800000000000000000000000000004024000000000000400800000000000000000000000000000000000000000000400800000000000000000005400000000000000040000000000000004008000000000000400000000000000040140000000000004008000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004008000000000000',
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
        ];
        yield 'ndrMultiPolygonMValueWithSrid' => [
            'value' => '0106000060e6100000010000000103000040020000000500000000000000000000000000000000000000000000000000084000000000000024400000000000000000000000000000084000000000000024400000000000002440000000000000084000000000000000000000000000002440000000000000084000000000000000000000000000000000000000000000084005000000000000000000004000000000000000400000000000000840000000000000004000000000000014400000000000000840000000000000144000000000000014400000000000000840000000000000144000000000000000400000000000000840000000000000004000000000000000400000000000000840',
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
        ];
        yield 'xdrMultiPolygonMValueWithSrid' => [
            'value' => '0060000006000010e6000000010040000003000000020000000500000000000000000000000000000000400800000000000040240000000000000000000000000000400800000000000040240000000000004024000000000000400800000000000000000000000000004024000000000000400800000000000000000000000000000000000000000000400800000000000000000005400000000000000040000000000000004008000000000000400000000000000040140000000000004008000000000000401400000000000040140000000000004008000000000000401400000000000040000000000000004008000000000000400000000000000040000000000000004008000000000000',
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
        ];
        yield 'ndrMultiPolygonZMValueWithSrid' => [
            'value' => '01060000e0e61000000100000001030000c00200000005000000000000000000000000000000000000000000000000000840000000000000004000000000000024400000000000000000000000000000084000000000000000400000000000002440000000000000244000000000000008400000000000000040000000000000000000000000000024400000000000000840000000000000004000000000000000000000000000000000000000000000084000000000000000400500000000000000000000400000000000000040000000000000084000000000000000400000000000000040000000000000144000000000000008400000000000000040000000000000144000000000000014400000000000000840000000000000004000000000000014400000000000000040000000000000084000000000000000400000000000000040000000000000004000000000000008400000000000000040',
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
        ];
        yield 'xdrMultiPolygonZMValueWithSrid' => [
            'value' => '00e0000006000010e60000000100c00000030000000200000005000000000000000000000000000000004008000000000000400000000000000040240000000000000000000000000000400800000000000040000000000000004024000000000000402400000000000040080000000000004000000000000000000000000000000040240000000000004008000000000000400000000000000000000000000000000000000000000000400800000000000040000000000000000000000540000000000000004000000000000000400800000000000040000000000000004000000000000000401400000000000040080000000000004000000000000000401400000000000040140000000000004008000000000000400000000000000040140000000000004000000000000000400800000000000040000000000000004000000000000000400000000000000040080000000000004000000000000000',
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
        ];
        yield 'ndrEmptyGeometryCollectionValue' => [
            'value' => '010700000000000000',
            'expected' => [
                'srid' => null,
                'type' => 'GEOMETRYCOLLECTION',
                'value' => [],
                'dimension' => null,
            ],
        ];
        yield 'ndrGeometryCollectionValueWithEmptyPoint' => [
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
        ];
        yield 'ndrGeometryCollectionValue' => [
            'value' => '01070000000300000001010000000000000000002440000000000000244001010000000000000000003E400000000000003E400102000000020000000000000000002E400000000000002E4000000000000034400000000000003440',
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
        ];
        yield 'xdrGeometryCollectionValue' => [
            'value' => '0000000007000000030000000001402400000000000040240000000000000000000001403E000000000000403E000000000000000000000200000002402E000000000000402E00000000000040340000000000004034000000000000',
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
        ];
        yield 'ndrGeometryCollectionZValue' => [
            'value' => '0107000080030000000101000080000000000000000000000000000000000000000000000000010200008002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f0107000080020000000101000080000000000000000000000000000000000000000000000000010200008002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrGeometryCollectionZValue' => [
            'value' => '00800000070000000300800000010000000000000000000000000000000000000000000000000080000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff000000000000000800000070000000200800000010000000000000000000000000000000000000000000000000080000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrGeometryCollectionMValue' => [
            'value' => '0107000040030000000101000040000000000000000000000000000000000000000000000000010200004002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f0107000040020000000101000040000000000000000000000000000000000000000000000000010200004002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrGeometryCollectionMValue' => [
            'value' => '00400000070000000300400000010000000000000000000000000000000000000000000000000040000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff000000000000000400000070000000200400000010000000000000000000000000000000000000000000000000040000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrGeometryCollectionZMValue' => [
            'value' => '01070000c00300000001010000c0000000000000000000000000000000000000000000000000000000000000f03f01020000c0020000000000000000000000000000000000000000000000000000000000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000084001070000c00200000001010000c0000000000000000000000000000000000000000000000000000000000000104001020000c0020000000000000000000000000000000000000000000000000000000000000000001440000000000000f03f000000000000f03f000000000000f03f0000000000001840',
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
        ];
        yield 'xdrGeometryCollectionZMValue' => [
            'value' => '00c00000070000000300c00000010000000000000000000000000000000000000000000000003ff000000000000000c00000020000000200000000000000000000000000000000000000000000000040000000000000003ff00000000000003ff00000000000003ff0000000000000400800000000000000c00000070000000200c0000001000000000000000000000000000000000000000000000000401000000000000000c00000020000000200000000000000000000000000000000000000000000000040140000000000003ff00000000000003ff00000000000003ff00000000000004018000000000000',
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
        ];
        yield 'ndrGeometryCollectionValueWithSrid' => [
            'value' => '0107000020E61000000300000001010000000000000000002440000000000000244001010000000000000000003E400000000000003E400102000000020000000000000000002E400000000000002E4000000000000034400000000000003440',
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
        ];
        yield 'xdrGeometryCollectionValueWithSrid' => [
            'value' => '0020000007000010E6000000030000000001402400000000000040240000000000000000000001403E000000000000403E000000000000000000000200000002402E000000000000402E00000000000040340000000000004034000000000000',
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
        ];
        yield 'ndrGeometryCollectionZValueWithSrid' => [
            'value' => '01070000a0e6100000030000000101000080000000000000000000000000000000000000000000000000010200008002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f0107000080020000000101000080000000000000000000000000000000000000000000000000010200008002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrGeometryCollectionZValueWithSrid' => [
            'value' => '00a0000007000010e60000000300800000010000000000000000000000000000000000000000000000000080000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff000000000000000800000070000000200800000010000000000000000000000000000000000000000000000000080000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrGeometryCollectionMValueWithSrid' => [
            'value' => '0107000060e6100000030000000101000040000000000000000000000000000000000000000000000000010200004002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f0107000040020000000101000040000000000000000000000000000000000000000000000000010200004002000000000000000000000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrGeometryCollectionMValueWithSrid' => [
            'value' => '0060000007000010e60000000300400000010000000000000000000000000000000000000000000000000040000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff000000000000000400000070000000200400000010000000000000000000000000000000000000000000000000040000002000000020000000000000000000000000000000000000000000000003ff00000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrGeometryCollectionZMValueWithSrid' => [
            'value' => '01070000e0e61000000300000001010000c0000000000000000000000000000000000000000000000000000000000000f03f01020000c0020000000000000000000000000000000000000000000000000000000000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000084001070000c00200000001010000c0000000000000000000000000000000000000000000000000000000000000104001020000c0020000000000000000000000000000000000000000000000000000000000000000001440000000000000f03f000000000000f03f000000000000f03f0000000000001840',
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
        ];
        yield 'xdrGeometryCollectionZMValueWithSrid' => [
            'value' => '00e0000007000010e60000000300c00000010000000000000000000000000000000000000000000000003ff000000000000000c00000020000000200000000000000000000000000000000000000000000000040000000000000003ff00000000000003ff00000000000003ff0000000000000400800000000000000c00000070000000200c0000001000000000000000000000000000000000000000000000000401000000000000000c00000020000000200000000000000000000000000000000000000000000000040140000000000003ff00000000000003ff00000000000003ff00000000000004018000000000000',
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
        ];
        yield 'ndrCircularStringValue' => [
            'value' => '01080000000300000000000000000000000000000000000000000000000000f03f000000000000f03f00000000000000400000000000000000',
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
        ];
        yield 'xdrCircularStringValue' => [
            'value' => '000000000800000003000000000000000000000000000000003ff00000000000003ff000000000000040000000000000000000000000000000',
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
        ];
        yield 'ndrCircularStringZValue' => [
            'value' => '01080000800300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f',
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
        ];
        yield 'xdrCircularStringZValue' => [
            'value' => '008000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000',
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
        ];
        yield 'ndrCircularStringMValue' => [
            'value' => '01080000400300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f',
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
        ];
        yield 'xdrCircularStringMValue' => [
            'value' => '004000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000',
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
        ];
        yield 'ndrCircularStringZMValue' => [
            'value' => '01080000c00300000000000000000000000000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000000000000000000000000f03f0000000000000040',
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
        ];
        yield 'xdrCircularStringZMValue' => [
            'value' => '00c000000800000003000000000000000000000000000000003ff000000000000040000000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000400000000000000000000000000000003ff00000000000004000000000000000',
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
        ];
        yield 'ndrCompoundCurveValue' => [
            'value' => '01090000000200000001080000000300000000000000000000000000000000000000000000000000f03f000000000000f03f00000000000000400000000000000000010200000002000000000000000000004000000000000000000000000000001040000000000000f03f',
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
        ];
        yield 'xdrCompoundCurveValue' => [
            'value' => '000000000900000002000000000800000003000000000000000000000000000000003ff00000000000003ff0000000000000400000000000000000000000000000000000000002000000024000000000000000000000000000000040100000000000003ff0000000000000',
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
        ];
        yield 'ndrCompoundCurveZValue' => [
            'value' => '01090000800200000001080000800300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f0102000080020000000000000000000040000000000000000000000000000000000000000000001040000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrCompoundCurveZValue' => [
            'value' => '008000000900000002008000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff000000000000000800000020000000240000000000000000000000000000000000000000000000040100000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrCompoundCurveMValue' => [
            'value' => '01090000400200000001080000400300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f0102000040020000000000000000000040000000000000000000000000000000000000000000001040000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrCompoundCurveMValue' => [
            'value' => '004000000900000002004000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff000000000000000400000020000000240000000000000000000000000000000000000000000000040100000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrCompoundCurveZMValue' => [
            'value' => '01090000c00200000001080000c00300000000000000000000000000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000000000000000000000000f03f000000000000004001020000c00200000000000000000000400000000000000000000000000000000000000000000000000000000000001040000000000000f03f000000000000f03f000000000000f03f',
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
        ];
        yield 'xdrCompoundCurveZMValue' => [
            'value' => '00c00000090000000200c000000800000003000000000000000000000000000000003ff000000000000040000000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000400000000000000000000000000000003ff0000000000000400000000000000000c000000200000002400000000000000000000000000000000000000000000000000000000000000040100000000000003ff00000000000003ff00000000000003ff0000000000000',
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
        ];
        yield 'ndrCurvePolygonValue' => [
            'value' => '010A0000000200000001080000000300000000000000000000000000000000000000000000000000084000000000000008400000000000001C400000000000001C400102000000030000000000000000001C400000000000001C400000000000002040000000000000204000000000000022400000000000002240',
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
        ];
        yield 'ndrCurvePolygonCompoundCurveValue' => [
            'value' => '010a0000000100000001090000000200000001080000000300000000000000000000000000000000000000000000000000f03f000000000000f03f0000000000000040000000000000000001020000000300000000000000000000400000000000000000000000000000f03f000000000000f0bf00000000000000000000000000000000',
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
        ];
        yield 'xdrCurvePolygonCompoundCurveValue' => [
            'value' => '000000000a00000001000000000900000002000000000800000003000000000000000000000000000000003ff00000000000003ff000000000000040000000000000000000000000000000000000000200000003400000000000000000000000000000003ff0000000000000bff000000000000000000000000000000000000000000000',
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
        ];
        yield 'ndrCurvePolygonZCompoundCurveValue' => [
            'value' => '010a0000800100000001090000800200000001080000800300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f01020000800300000000000000000000400000000000000000000000000000f03f000000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
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
        ];
        yield 'xdrCurvePolygonZCompoundCurveValue' => [
            'value' => '008000000a00000001008000000900000002008000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000008000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
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
        ];
        yield 'ndrCurvePolygonMCompoundCurveValue' => [
            'value' => '010a0000400100000001090000400200000001080000400300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f01020000400300000000000000000000400000000000000000000000000000f03f000000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
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
        ];
        yield 'xdrCurvePolygonMCompoundCurveValue' => [
            'value' => '004000000a00000001004000000900000002004000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000004000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
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
        ];
        yield 'ndrCurvePolygonZMCompoundCurveValue' => [
            'value' => '010a0000c00100000001090000c00200000001080000c00300000000000000000000000000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000000000000000000000000f03f000000000000004001020000c00300000000000000000000400000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f0bf000000000000f03f000000000000f03f00000000000000000000000000000000000000000000f03f0000000000000040',
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
        ];
        yield 'xdrCurvePolygonZMVCompoundCurvealue' => [
            'value' => '00c000000a0000000100c00000090000000200c000000800000003000000000000000000000000000000003ff000000000000040000000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000400000000000000000000000000000003ff0000000000000400000000000000000c000000200000003400000000000000000000000000000003ff000000000000040000000000000003ff0000000000000bff00000000000003ff00000000000003ff0000000000000000000000000000000000000000000003ff00000000000004000000000000000',
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
        ];
        yield 'ndrMultiCurveValue' => [
            'value' => '010B0000000200000001080000000300000000000000000000000000000000000000000000000000084000000000000008400000000000001C400000000000001C400102000000030000000000000000001C400000000000001C400000000000002040000000000000204000000000000022400000000000002240',
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
        ];
        yield 'ndrMultiCurveCompoundCurveValue' => [
            'value' => '010b0000000100000001090000000200000001080000000300000000000000000000000000000000000000000000000000f03f000000000000f03f0000000000000040000000000000000001020000000300000000000000000000400000000000000000000000000000f03f000000000000f0bf00000000000000000000000000000000',
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
        ];
        yield 'xdrMultiCurveCompoundCurveValue' => [
            'value' => '000000000b00000001000000000900000002000000000800000003000000000000000000000000000000003ff00000000000003ff000000000000040000000000000000000000000000000000000000200000003400000000000000000000000000000003ff0000000000000bff000000000000000000000000000000000000000000000',
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
        ];
        yield 'ndrMultiCurveZCompoundCurveValue' => [
            'value' => '010b0000800100000001090000800200000001080000800300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f01020000800300000000000000000000400000000000000000000000000000f03f000000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
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
        ];
        yield 'xdrMultiCurveZCompoundCurveValue' => [
            'value' => '008000000b00000001008000000900000002008000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000008000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
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
        ];
        yield 'ndrMultiCurveMCompoundCurveValue' => [
            'value' => '010b0000400100000001090000400200000001080000400300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f01020000400300000000000000000000400000000000000000000000000000f03f000000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f',
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
        ];
        yield 'xdrMultiCurveMCompoundCurveValue' => [
            'value' => '004000000b00000001004000000900000002004000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000004000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000',
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
        ];
        yield 'ndrMultiCurveZMCompoundCurveValue' => [
            'value' => '010b0000c00100000001090000c00200000001080000c00300000000000000000000000000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000000000000000000000000f03f000000000000004001020000c00300000000000000000000400000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f0bf000000000000f03f000000000000f03f00000000000000000000000000000000000000000000f03f0000000000000040',
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
        ];
        yield 'xdrMultiCurveZMCompoundCurveValue' => [
            'value' => '00c000000b0000000100c00000090000000200c000000800000003000000000000000000000000000000003ff000000000000040000000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000400000000000000000000000000000003ff0000000000000400000000000000000c000000200000003400000000000000000000000000000003ff000000000000040000000000000003ff0000000000000bff00000000000003ff00000000000003ff0000000000000000000000000000000000000000000003ff00000000000004000000000000000',
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
        ];
        yield 'ndrMultiSurfaceValue' => [
            'value' => '010c00000002000000010a0000000100000001090000000200000001080000000300000000000000000000000000000000000000000000000000f03f000000000000f03f0000000000000040000000000000000001020000000300000000000000000000400000000000000000000000000000f03f000000000000f0bf00000000000000000000000000000000010300000001000000050000000000000000002440000000000000244000000000000024400000000000002840000000000000284000000000000028400000000000002840000000000000244000000000000024400000000000002440',
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
        ];
        yield 'xdrMultiSurfaceValue' => [
            'value' => '000000000c00000002000000000a00000001000000000900000002000000000800000003000000000000000000000000000000003ff00000000000003ff000000000000040000000000000000000000000000000000000000200000003400000000000000000000000000000003ff0000000000000bff000000000000000000000000000000000000000000000000000000300000001000000054024000000000000402400000000000040240000000000004028000000000000402800000000000040280000000000004028000000000000402400000000000040240000000000004024000000000000',
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
        ];
        yield 'ndrMultiSurfaceZValue' => [
            'value' => '010c00008002000000010a0000800100000001090000800200000001080000800300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f01020000800300000000000000000000400000000000000000000000000000f03f000000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f01030000800100000005000000000000000000244000000000000024400000000000002440000000000000244000000000000028400000000000002440000000000000284000000000000028400000000000002440000000000000284000000000000024400000000000002440000000000000244000000000000024400000000000002440',
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
        ];
        yield 'xdrMultiSurfaceZValue' => [
            'value' => '008000000c00000002008000000a00000001008000000900000002008000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000008000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff000000000000000800000030000000100000005402400000000000040240000000000004024000000000000402400000000000040280000000000004024000000000000402800000000000040280000000000004024000000000000402800000000000040240000000000004024000000000000402400000000000040240000000000004024000000000000',
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
        ];
        yield 'ndrMultiSurfaceMValue' => [
            'value' => '010c00004002000000010a0000400100000001090000400200000001080000400300000000000000000000000000000000000000000000000000f03f000000000000f03f000000000000f03f000000000000f03f00000000000000400000000000000000000000000000f03f01020000400300000000000000000000400000000000000000000000000000f03f000000000000f03f000000000000f0bf000000000000f03f00000000000000000000000000000000000000000000f03f01030000400100000005000000000000000000244000000000000024400000000000002440000000000000244000000000000028400000000000002440000000000000284000000000000028400000000000002440000000000000284000000000000024400000000000002440000000000000244000000000000024400000000000002440',
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
        ];
        yield 'xdrMultiSurfaceMValue' => [
            'value' => '004000000c00000002004000000a00000001004000000900000002004000000800000003000000000000000000000000000000003ff00000000000003ff00000000000003ff00000000000003ff0000000000000400000000000000000000000000000003ff0000000000000004000000200000003400000000000000000000000000000003ff00000000000003ff0000000000000bff00000000000003ff0000000000000000000000000000000000000000000003ff000000000000000400000030000000100000005402400000000000040240000000000004024000000000000402400000000000040280000000000004024000000000000402800000000000040280000000000004024000000000000402800000000000040240000000000004024000000000000402400000000000040240000000000004024000000000000',
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
        ];
        yield 'ndrMultiSurfaceZMValue' => [
            'value' => '010c0000c002000000010a0000c00100000001090000c00200000001080000c00300000000000000000000000000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f03f000000000000f03f000000000000004000000000000000400000000000000000000000000000f03f000000000000004001020000c00300000000000000000000400000000000000000000000000000f03f0000000000000040000000000000f03f000000000000f0bf000000000000f03f000000000000f03f00000000000000000000000000000000000000000000f03f000000000000004001030000c0010000000500000000000000000024400000000000002440000000000000244000000000000024400000000000002440000000000000284000000000000024400000000000002440000000000000284000000000000028400000000000002440000000000000244000000000000028400000000000002440000000000000244000000000000024400000000000002440000000000000244000000000000024400000000000002440',
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
        ];
        yield 'xdrMultiSurfaceZMValue' => [
            'value' => '00c000000c0000000200c000000a0000000100c00000090000000200c000000800000003000000000000000000000000000000003ff000000000000040000000000000003ff00000000000003ff00000000000003ff00000000000004000000000000000400000000000000000000000000000003ff0000000000000400000000000000000c000000200000003400000000000000000000000000000003ff000000000000040000000000000003ff0000000000000bff00000000000003ff00000000000003ff0000000000000000000000000000000000000000000003ff0000000000000400000000000000000c0000003000000010000000540240000000000004024000000000000402400000000000040240000000000004024000000000000402800000000000040240000000000004024000000000000402800000000000040280000000000004024000000000000402400000000000040280000000000004024000000000000402400000000000040240000000000004024000000000000402400000000000040240000000000004024000000000000',
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
        ];
        yield 'ndrPolyhedralSurfaceZValue' => [
            'value' => '010f000080050000000103000080010000000500000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000144000000000000000000000000000002e40000000000000144000000000000000000000000000002e4000000000000000000000000000000000000000000000000000000000000000000103000080010000000500000000000000000000000000000000000000000000000000000000000000000000000000000000002e40000000000000000000000000000024400000000000002e400000000000000000000000000000244000000000000000000000000000000000000000000000000000000000000000000000000000000000010300008001000000050000000000000000000000000000000000000000000000000000000000000000002440000000000000000000000000000000000000000000002440000000000000000000000000000014400000000000000000000000000000000000000000000014400000000000000000000000000000000000000000000000000103000080010000000500000000000000000024400000000000000000000000000000000000000000000024400000000000002e40000000000000000000000000000024400000000000002e4000000000000014400000000000002440000000000000000000000000000014400000000000002440000000000000000000000000000000000103000080010000000500000000000000000000000000000000002e40000000000000000000000000000000000000000000002e40000000000000144000000000000024400000000000002e40000000000000144000000000000024400000000000002e40000000000000000000000000000000000000000000002e400000000000000000',
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
        ];
        yield 'ndrPolyhedralSurfaceMValue' => [
            'value' => '010f000040050000000103000040010000000500000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000144000000000000000000000000000002e40000000000000144000000000000000000000000000002e4000000000000000000000000000000000000000000000000000000000000000000103000040010000000500000000000000000000000000000000000000000000000000000000000000000000000000000000002e40000000000000000000000000000024400000000000002e400000000000000000000000000000244000000000000000000000000000000000000000000000000000000000000000000000000000000000010300004001000000050000000000000000000000000000000000000000000000000000000000000000002440000000000000000000000000000000000000000000002440000000000000000000000000000014400000000000000000000000000000000000000000000014400000000000000000000000000000000000000000000000000103000040010000000500000000000000000024400000000000000000000000000000000000000000000024400000000000002e40000000000000000000000000000024400000000000002e4000000000000014400000000000002440000000000000000000000000000014400000000000002440000000000000000000000000000000000103000040010000000500000000000000000000000000000000002e40000000000000000000000000000000000000000000002e40000000000000144000000000000024400000000000002e40000000000000144000000000000024400000000000002e40000000000000000000000000000000000000000000002e400000000000000000',
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
        ];
        yield 'xdrGeometryCollectionValue2' => [
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
        ];
        yield 'xdrMultiPointValue2' => [
            'value' => '01040000000200000001010000000000000000000000000000000000f03f010100000000000000000000400000000000000840',
            'expected' => [
                'type' => 'MULTIPOINT',
                'value' => [[0, 1], [2, 3]],
                'srid' => null,
                'dimension' => null,
            ],
        ];
        yield 'xdrMultiLineStringValue2' => [
            'value' => '0105000000020000000102000000020000000000000000000000000000000000f03f000000000000004000000000000008400102000000020000000000000000001040000000000000144000000000000018400000000000001c40',
            'expected' => [
                'type' => 'MULTILINESTRING',
                'value' => [[[0, 1], [2, 3]], [[4, 5], [6, 7]]],
                'srid' => null,
                'dimension' => null,
            ],
        ];
        yield 'xdrMultiPolygonValue2' => [
            'value' => '01060000000200000001030000000200000005000000000000000000000000000000000000000000000000000000000000000000244000000000000024400000000000002440000000000000244000000000000000000000000000000000000000000000000005000000000000000000f03f000000000000f03f000000000000f03f0000000000002240000000000000224000000000000022400000000000002240000000000000f03f000000000000f03f000000000000f03f0103000000010000000500000000000000000022c0000000000000000000000000000022c00000000000002440000000000000f0bf0000000000002440000000000000f0bf000000000000000000000000000022c00000000000000000',
            'expected' => [
                'type' => 'MULTIPOLYGON',
                'value' => [[[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]], [[1, 1], [1, 9], [9, 9], [9, 1], [1, 1]]], [[[-9, 0], [-9, 10], [-1, 10], [-1, 0], [-9, 0]]]],
                'srid' => null,
                'dimension' => null,
            ],
        ];
        yield 'xdrMultiPointZOGCValue' => [
            'value' => '01ec0300000200000001e90300000000000000000000000000000000f03f000000000000004001e9030000000000000000084000000000000010400000000000001440',
            'expected' => [
                'type' => 'MULTIPOINT',
                'value' => [[0, 1, 2], [3, 4, 5]],
                'srid' => null,
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrMultiLineStringZOGCValue' => [
            'value' => '01ed0300000200000001ea030000020000000000000000000000000000000000f03f000000000000004000000000000008400000000000001040000000000000144001ea0300000200000000000000000018400000000000001c400000000000002040000000000000224000000000000024400000000000002640',
            'expected' => [
                'type' => 'MULTILINESTRING',
                'value' => [[[0, 1, 2], [3, 4, 5]], [[6, 7, 8], [9, 10, 11]]],
                'srid' => null,
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrMultiPolygonZOGCValue' => [
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
        ];
        yield 'xdrPointValue2' => [
            'value' => '0101000000000000000000f03f0000000000000040',
            'expected' => [
                'type' => 'POINT',
                'value' => [1, 2],
                'srid' => null,
                'dimension' => null,
            ],
        ];
        yield 'xdrLineStringValue2' => [
            'value' => '010200000002000000000000000000f03f000000000000004000000000000008400000000000001040',
            'expected' => [
                'type' => 'LINESTRING',
                'value' => [[1, 2], [3, 4]],
                'srid' => null,
                'dimension' => null,
            ],
        ];
        yield 'xdrPolygonValue2' => [
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
        ];
        yield 'xdrPointZOGCValue2' => [
            'value' => '01e9030000000000000000f03f00000000000000400000000000000840',
            'expected' => [
                'type' => 'POINT',
                'value' => [1, 2, 3],
                'srid' => null,
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrLineStringZOGCValue' => [
            'value' => '01ea03000002000000000000000000f03f00000000000000400000000000000840000000000000104000000000000014400000000000001840',
            'expected' => [
                'type' => 'LINESTRING',
                'value' => [[1, 2, 3], [4, 5, 6]],
                'srid' => null,
                'dimension' => 'Z',
            ],
        ];
        yield 'xdrPolygonZOGCValue' => [
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
     * @param class-string<ExceptionInterface> $exception
     *
     * @dataProvider badBinaryData
     */
    public function testBadBinaryData(string $value, string $exception, string $message): void
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
     * @param array{srid: ?int, type:string, value:array<int|float|int[]|float[]>, dimension: ?string} $expected
     *
     * @dataProvider goodBinaryData
     */
    public function testParserBinary(string $value, array $expected): void
    {
        $parser = new Parser(pack('H*', $value));
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param array{srid: ?int, type:string, value:array<int|float|int[]|float[]>, dimension: ?string} $expected
     *
     * @dataProvider goodBinaryData
     */
    public function testParserPrependLower0XHex(string $value, array $expected): void
    {
        $parser = new Parser('0x'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param array{srid: ?int, type:string, value:array<int|float|int[]|float[]>, dimension: ?string} $expected
     *
     * @dataProvider goodBinaryData
     */
    public function testParserPrependLowerXHex(string $value, array $expected): void
    {
        $parser = new Parser('x'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param array{srid: ?int, type:string, value:array<int|float|int[]|float[]>, dimension: ?string} $expected
     *
     * @dataProvider goodBinaryData
     */
    public function testParserPrependUpper0XHex(string $value, array $expected): void
    {
        $parser = new Parser('0X'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param array{srid: ?int, type:string, value:array<int|float|int[]|float[]>, dimension: ?string} $expected
     *
     * @dataProvider goodBinaryData
     */
    public function testParserPrependUpperXHex(string $value, array $expected): void
    {
        $parser = new Parser('X'.$value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    /**
     * @param array{srid: ?int, type:string, value:array<int|float|int[]|float[]>, dimension: ?string} $expected
     *
     * @dataProvider goodBinaryData
     */
    public function testParserRawHex(string $value, array $expected): void
    {
        $parser = new Parser($value);
        $actual = $parser->parse();

        $this->assertEquals($expected, $actual);
    }

    public function testReusedParser(): void
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
