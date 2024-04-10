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

namespace LongitudeOne\Geo\WKB;

use LongitudeOne\Geo\WKB\Exception\ExceptionInterface;
use LongitudeOne\Geo\WKB\Exception\UnexpectedValueException;

/**
 * Parser for WKB/EWKB spatial object data.
 */
class Parser
{
    public const TYPE_CIRCULARSTRING = 'CircularString';
    public const TYPE_COMPOUNDCURVE = 'CompoundCurve';
    public const TYPE_CURVEPOLYGON = 'CurvePolygon';

    public const TYPE_GEOMETRY = 'Geometry';
    public const TYPE_GEOMETRYCOLLECTION = 'GeometryCollection';
    public const TYPE_LINESTRING = 'LineString';
    public const TYPE_MULTICURVE = 'MultiCurve';
    public const TYPE_MULTILINESTRING = 'MultiLineString';
    public const TYPE_MULTIPOINT = 'MultiPoint';
    public const TYPE_MULTIPOLYGON = 'MultiPolygon';
    public const TYPE_MULTISURFACE = 'MultiSurface';
    public const TYPE_POINT = 'Point';
    public const TYPE_POLYGON = 'Polygon';
    public const TYPE_POLYHEDRALSURFACE = 'PolyhedralSurface';
    public const TYPE_TIN = 'Tin';
    public const TYPE_TRIANGLE = 'Triangle';

    public const WKB_FLAG_M = 0x40000000;
    public const WKB_FLAG_SRID = 0x20000000;
    public const WKB_FLAG_Z = 0x80000000;

    public const WKB_TYPE_CIRCULARSTRING = 0x00000008;
    public const WKB_TYPE_COMPOUNDCURVE = 0x00000009;
    public const WKB_TYPE_CURVE = 0x0000000D;
    public const WKB_TYPE_CURVEPOLYGON = 0x0000000A;
    public const WKB_TYPE_GEOMETRY = 0x00000000;
    public const WKB_TYPE_GEOMETRYCOLLECTION = 0x00000007;
    public const WKB_TYPE_LINESTRING = 0x00000002;
    public const WKB_TYPE_MULTICURVE = 0x0000000B;
    public const WKB_TYPE_MULTILINESTRING = 0x00000005;
    public const WKB_TYPE_MULTIPOINT = 0x00000004;
    public const WKB_TYPE_MULTIPOLYGON = 0x00000006;
    public const WKB_TYPE_MULTISURFACE = 0x0000000C;
    public const WKB_TYPE_POINT = 0x00000001;
    public const WKB_TYPE_POLYGON = 0x00000003;
    public const WKB_TYPE_POLYHEDRALSURFACE = 0x0000000F;
    public const WKB_TYPE_SURFACE = 0x0000000E;
    public const WKB_TYPE_TIN = 0x00000010;
    public const WKB_TYPE_TRIANGLE = 0x00000011;

    private ?int $dimensions;

    private int $pointSize = 2;

    private Reader $reader;

    private int $type;

    /**
     * @param string $input
     *
     * @throws UnexpectedValueException
     */
    public function __construct($input = null)
    {
        $this->reader = new Reader();

        if (null !== $input) {
            if (!is_string($input)) {
                trigger_error(
                    sprintf('%s: Since longitudeone/geo-wkb-parser 2.1, Argument 1 passed to __construct() must be of the type string, %s given, called in %s on line %d',
                        static::class,
                        gettype($input),
                        __FILE__,
                        __LINE__
                    ),
                    E_USER_DEPRECATED
                );
            }

            $this->reader->load((string) $input);
        }
    }

    /**
     * Parse input data.
     *
     * @param string $input
     *
     * @return array{type:string, srid: ?int, value: (float|int)[]|(float|int)[][]|(float|int)[][][]|(float|int)[][][][]|(float|int)[][][][][]|array{type: string, value:(float|int)[][]}[]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]|array{type: string, value:(float|int)[][][]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]}[]|array{type: string, value:(float|int)[][][]}[]|array{type:string, value:(float|int)[]|(float|int)[][]|(float|int)[][][]|(float|int)[][][][]|(float|int)[][][][][]|array{type: string, value:(float|int)[][]}[]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]|array{type: string, value:(float|int)[][][]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]}[]|array{type: string, value:(float|int)[][][]}[]}[], dimension: ?string}
     *
     * @throws ExceptionInterface
     */
    public function parse($input = null): array
    {
        if (null !== $input) {
            if (!is_string($input)) {
                trigger_error(
                    sprintf('%s: Since longitudeone/geo-wkb-parser 2.1, Argument 1 passed to parse() must be of the type string, %s given, called in %s on line %d',
                        static::class,
                        gettype($input),
                        __FILE__,
                        __LINE__
                    ),
                    E_USER_DEPRECATED
                );
            }

            $this->reader->load((string) $input);
        }

        return $this->readGeometry();
    }

    /**
     * Parse CIRCULARSTRING value.
     *
     * @return (float|int)[][]
     *
     * @throws UnexpectedValueException
     */
    private function circularString(): array
    {
        return $this->readPoints($this->readCount());
    }

    /**
     * Parse COMPOUNDCURVE value.
     *
     * @return array{type: string, value:(float|int)[][]}[]
     *
     * @throws UnexpectedValueException
     */
    private function compoundCurve(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            $value = match ($type) {
                $this->getDimensionedPrimitive(self::WKB_TYPE_LINESTRING),
                $this->getDimensionedPrimitive(self::WKB_TYPE_CIRCULARSTRING) => $this->readPoints($this->readCount()),
                default => throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_COMPOUNDCURVE, [self::WKB_TYPE_LINESTRING, self::WKB_TYPE_CIRCULARSTRING])),
            };

            $values[] = [
                'type' => $this->getTypeName($type),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * Parse CURVEPOLYGON value.
     *
     * @return array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]
     *
     * @throws UnexpectedValueException
     */
    private function curvePolygon(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            switch ($type) {
                case $this->getDimensionedPrimitive(self::WKB_TYPE_LINESTRING):
                case $this->getDimensionedPrimitive(self::WKB_TYPE_CIRCULARSTRING):
                    $value = $this->readPoints($this->readCount());
                    break;
                case $this->getDimensionedPrimitive(self::WKB_TYPE_COMPOUNDCURVE):
                    $value = $this->compoundCurve();
                    break;
                default:
                    throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_CURVEPOLYGON, [self::WKB_TYPE_LINESTRING, self::WKB_TYPE_CIRCULARSTRING, self::WKB_TYPE_COMPOUNDCURVE]));
            }

            $values[] = [
                'type' => $this->getTypeName($type),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * Parse GEOMETRYCOLLECTION value.
     * The type of each geometry is stored in the 'type' key of the returned array.
     * The value of each geometry is stored in the 'value' key of the returned array.
     * This value can be point|line-string|multiPoint|polygon|multiLineString|multiPolygon|compoundCurve||multiCurve|multiSurface| polyhedralSurface,
     * OR a geometryCollection of all these previous types because Geometry collections are recursive.
     * So, geometry collections can contain geometry collections.
     *
     * @return array{type:string, value:(float|int)[]|(float|int)[][]|(float|int)[][][]|(float|int)[][][][]|(float|int)[][][][][]|array{type: string, value:(float|int)[][]}[]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]|array{type: string, value:(float|int)[][][]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]}[]|array{type: string, value:(float|int)[][][]}[]}[]
     *
     * @throws UnexpectedValueException
     */
    private function geometryCollection(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();
            $typeName = $this->getTypeName($type);

            $value = match ($typeName) {
                strtoupper(self::TYPE_POINT) => $this->point(),
                strtoupper(self::TYPE_LINESTRING) => $this->lineString(),
                strtoupper(self::TYPE_POLYGON) => $this->polygon(),
                strtoupper(self::TYPE_MULTIPOINT) => $this->multiPoint(),
                strtoupper(self::TYPE_MULTILINESTRING) => $this->multiLineString(),
                strtoupper(self::TYPE_MULTIPOLYGON) => $this->multiPolygon(),
                strtoupper(self::TYPE_GEOMETRYCOLLECTION) => $this->geometryCollection(),
                strtoupper(self::TYPE_CIRCULARSTRING) => $this->circularString(),
                strtoupper(self::TYPE_COMPOUNDCURVE) => $this->compoundCurve(),
                strtoupper(self::TYPE_CURVEPOLYGON) => $this->curvePolygon(),
                strtoupper(self::TYPE_MULTICURVE) => $this->multiCurve(),
                strtoupper(self::TYPE_MULTISURFACE) => $this->multiSurface(),
                strtoupper(self::TYPE_POLYHEDRALSURFACE) => $this->polyhedralSurface(),
                default => throw new UnexpectedValueException(sprintf('Unsupported typeName "%s" with type (0x%2$x)', $typeName, $type)),
            };

            $values[] = [
                'type' => $typeName,
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * @param int[] $expectedTypes
     *
     * @return string
     */
    private function getBadTypeInTypeMessage(int $childType, int $parentType, array $expectedTypes)
    {
        if ($this->type !== $parentType) {
            $parentType = $this->type;
        }

        $message = sprintf(
            ' %s with dimensions 0x%X (%2$d) in %3$s, expected ',
            $this->getTypeName($childType),
            $this->getDimensions($childType),
            $this->getTypeName($parentType)
        );

        if (!in_array($this->getTypePrimitive($childType), $expectedTypes, true)) {
            if (1 === count($expectedTypes)) {
                $message .= $this->getTypeName($expectedTypes[0]);
            } else {
                $last = $this->getTypeName(array_pop($expectedTypes));
                $message .= implode(', ', array_map([$this, 'getTypeName'], $expectedTypes)).' or '.$last;
            }

            $message = 'Unexpected'.$message.' with ';
        } else {
            $message = 'Bad'.$message;
        }

        return $message.sprintf('dimensions 0x%X (%1$d)', $this->dimensions);
    }

    /**
     * @param int $type
     *
     * @return int
     */
    private function getDimensionedPrimitive($type)
    {
        if (null === $this->dimensions) {
            return $type;
        }

        if ($this->dimensions & (self::WKB_FLAG_Z | self::WKB_FLAG_M)) {
            return $type | $this->dimensions;
        }

        return $type + $this->dimensions;
    }

    private function getDimensions(int $type): ?int
    {
        if ($this->is2D($type)) {
            return null;
        }

        if ($type & (self::WKB_FLAG_SRID | self::WKB_FLAG_M | self::WKB_FLAG_Z)) {
            return $type & (self::WKB_FLAG_M | self::WKB_FLAG_Z);
        }

        return $type - ($type % 1000);
    }

    /**
     * @throws UnexpectedValueException
     */
    private function getDimensionType(?int $dimensions): string
    {
        if ($this->is2D($dimensions)) {
            return '';
        }

        return match ($dimensions) {
            1000, self::WKB_FLAG_Z => 'Z',
            2000, self::WKB_FLAG_M => 'M',
            3000, self::WKB_FLAG_M | self::WKB_FLAG_Z => 'ZM',
            default => throw new UnexpectedValueException(sprintf('%s with unsupported dimensions 0x%2$X (%2$d)', $this->getTypeName($this->type), $dimensions)),
        };
    }

    /**
     * Get name of data type.
     *
     * @param int $type
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    private function getTypeName($type)
    {
        $typeName = match ($this->getTypePrimitive($type)) {
            self::WKB_TYPE_POINT => self::TYPE_POINT,
            self::WKB_TYPE_LINESTRING => self::TYPE_LINESTRING,
            self::WKB_TYPE_POLYGON => self::TYPE_POLYGON,
            self::WKB_TYPE_MULTIPOINT => self::TYPE_MULTIPOINT,
            self::WKB_TYPE_MULTILINESTRING => self::TYPE_MULTILINESTRING,
            self::WKB_TYPE_MULTIPOLYGON => self::TYPE_MULTIPOLYGON,
            self::WKB_TYPE_GEOMETRYCOLLECTION => self::TYPE_GEOMETRYCOLLECTION,
            self::WKB_TYPE_CIRCULARSTRING => self::TYPE_CIRCULARSTRING,
            self::WKB_TYPE_COMPOUNDCURVE => self::TYPE_COMPOUNDCURVE,
            self::WKB_TYPE_CURVEPOLYGON => self::TYPE_CURVEPOLYGON,
            self::WKB_TYPE_MULTICURVE => self::TYPE_MULTICURVE,
            self::WKB_TYPE_MULTISURFACE => self::TYPE_MULTISURFACE,
            self::WKB_TYPE_POLYHEDRALSURFACE, self::WKB_TYPE_POLYHEDRALSURFACE | self::WKB_FLAG_Z => self::TYPE_POLYHEDRALSURFACE,
            default => throw new UnexpectedValueException(sprintf('Unsupported WKB type "%1$d" (0x%1$x)', $this->type)),
        };

        return strtoupper($typeName);
    }

    /**
     * @param int $type
     *
     * @return int
     */
    private function getTypePrimitive($type)
    {
        if ($this->is2D($type)) {
            return $type;
        }

        if ($type > 0xFFFF) {
            return $type & 0xFF;
        }

        return $type % 1000;
    }

    /**
     * Check type for flag.
     *
     * @param int $type
     * @param int $flag
     *
     * @return bool
     */
    private function hasFlag($type, $flag)
    {
        return ($type & $flag) === $flag;
    }

    private function is2D(?int $type): bool
    {
        if (null === $type) {
            return true;
        }

        return $type < 0x20; // FIXME : 32 is a magic number
    }

    /**
     * Parse LINESTRING value.
     *
     * @return (float|int)[][]
     *
     * @throws UnexpectedValueException
     */
    private function lineString(): array
    {
        return $this->readPoints($this->readCount());
    }

    /**
     * Parse MULTICURVE value.
     *
     * @return array{type:string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]
     *
     * @throws UnexpectedValueException
     */
    private function multiCurve(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            $value = match ($type) {
                $this->getDimensionedPrimitive(self::WKB_TYPE_LINESTRING),
                $this->getDimensionedPrimitive(self::WKB_TYPE_CIRCULARSTRING) => $this->readPoints($this->readCount()),
                $this->getDimensionedPrimitive(self::WKB_TYPE_COMPOUNDCURVE) => $this->compoundCurve(),
                default => throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_MULTICURVE, [self::WKB_TYPE_LINESTRING, self::WKB_TYPE_CIRCULARSTRING, self::WKB_TYPE_COMPOUNDCURVE])),
            };

            $values[] = [
                'type' => $this->getTypeName($type),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * Parse MULTILINESTRING value.
     *
     * @return (float|int)[][][]
     *
     * @throws UnexpectedValueException
     */
    private function multiLineString(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            if ($this->getDimensionedPrimitive(self::WKB_TYPE_LINESTRING) !== $type) {
                throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_MULTILINESTRING, [self::WKB_TYPE_LINESTRING]));
            }

            $values[] = $this->readPoints($this->readCount());
        }

        return $values;
    }

    /**
     * Parse MULTIPOINT value.
     *
     * @return (float|int)[][]
     *
     * @throws UnexpectedValueException
     */
    private function multiPoint(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            if ($this->getDimensionedPrimitive(self::WKB_TYPE_POINT) !== $type) {
                throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_MULTIPOINT, [self::WKB_TYPE_POINT]));
            }

            $values[] = $this->point();
        }

        return $values;
    }

    /**
     * Parse MULTIPOLYGON value.
     *
     * @return float[][][][]|int[][][][]
     *
     * @throws UnexpectedValueException
     */
    private function multiPolygon(): array
    {
        $count = $this->readCount();
        $values = [];

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            if ($this->getDimensionedPrimitive(self::WKB_TYPE_POLYGON) !== $type) {
                throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_MULTIPOLYGON, [self::WKB_TYPE_POLYGON]));
            }

            $values[] = $this->readLinearRings($this->readCount());
        }

        return $values;
    }

    /**
     * Parse MULTISURFACE value.
     *
     * @return array{type: string, value:float[][][]|int[][][]|array{type: string, value:float[][]|int[][]|array{type: string, value:float[][]|int[][]}[]}[]}[]
     *
     * @throws UnexpectedValueException
     */
    private function multiSurface(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            switch ($type) {
                case $this->getDimensionedPrimitive(self::WKB_TYPE_POLYGON):
                    $value = $this->polygon();
                    break;
                case $this->getDimensionedPrimitive(self::WKB_TYPE_CURVEPOLYGON):
                    $value = $this->curvePolygon();
                    break;
                default:
                    throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_MULTISURFACE, [self::WKB_TYPE_POLYGON, self::WKB_TYPE_CURVEPOLYGON]));
            }

            $values[] = [
                'type' => $this->getTypeName($type),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * Parse POINT values.
     *
     * @return (float|int)[]
     *
     * @throws UnexpectedValueException
     */
    private function point()
    {
        return $this->reader->readFloats($this->pointSize);
    }

    /**
     * Parse POLYGON value.
     *
     * @return (float|int)[][][]
     *
     * @throws UnexpectedValueException
     */
    private function polygon(): array
    {
        return $this->readLinearRings($this->readCount());
    }

    /**
     * Parse POLYHEDRALSURFACE value.
     *
     * @return array{type: string, value:(float|int)[][][]}[]
     *
     * @throws UnexpectedValueException
     */
    private function polyhedralSurface(): array
    {
        $values = [];
        $count = $this->readCount();

        for ($i = 0; $i < $count; ++$i) {
            $this->readByteOrder();

            $type = $this->readType();

            switch ($type) {
                case $this->getDimensionedPrimitive(self::WKB_TYPE_POLYGON):
                    $value = $this->polygon();
                    break;
                    // is polygon the only one?
                default:
                    throw new UnexpectedValueException($this->getBadTypeInTypeMessage($type, self::WKB_TYPE_POLYHEDRALSURFACE, [self::WKB_TYPE_POLYGON]));
            }

            $values[] = [
                'type' => $this->getTypeName($type),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * Parse data byte order.
     *
     * @throws UnexpectedValueException
     */
    private function readByteOrder(): int
    {
        return $this->reader->readByteOrder();
    }

    /**
     * @throws UnexpectedValueException
     */
    private function readCount(): int
    {
        $count = $this->reader->readLong();

        if (!is_int($count)) {
            throw new UnexpectedValueException('Invalid count value');
        }

        return $count;
    }

    /**
     * Parse geometry data.
     *
     * @return array{type:string, srid: ?int, value: (float|int)[]|(float|int)[][]|(float|int)[][][]|(float|int)[][][][]|(float|int)[][][][][]|array{type: string, value:(float|int)[][]}[]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]|array{type: string, value:(float|int)[][][]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]}[]|array{type: string, value:(float|int)[][][]}[]|array{type:string, value:(float|int)[]|(float|int)[][]|(float|int)[][][]|(float|int)[][][][]|(float|int)[][][][][]|array{type: string, value:(float|int)[][]}[]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]|array{type: string, value:(float|int)[][][]|array{type: string, value:(float|int)[][]|array{type: string, value:(float|int)[][]}[]}[]}[]|array{type: string, value:(float|int)[][][]}[]}[], dimension: ?string}
     *
     * @throws ExceptionInterface
     */
    private function readGeometry(): array
    {
        $srid = null;

        try {
            $this->readByteOrder();
            $this->type = $this->readType();

            if ($this->hasFlag($this->type, self::WKB_FLAG_SRID)) {
                $srid = $this->readSrid();
            }

            $this->dimensions = $this->getDimensions($this->type);
            $this->pointSize = 2 + strlen($this->getDimensionType($this->dimensions));
            $typeName = $this->getTypeName($this->type);

            $value = match (strtoupper($typeName)) {
                strtoupper(self::TYPE_POINT) => $this->point(),
                strtoupper(self::TYPE_LINESTRING) => $this->lineString(),
                strtoupper(self::TYPE_POLYGON) => $this->polygon(),
                strtoupper(self::TYPE_MULTIPOINT) => $this->multiPoint(),
                strtoupper(self::TYPE_MULTILINESTRING) => $this->multiLineString(),
                strtoupper(self::TYPE_MULTIPOLYGON) => $this->multiPolygon(),
                strtoupper(self::TYPE_GEOMETRYCOLLECTION) => $this->geometryCollection(),
                strtoupper(self::TYPE_CIRCULARSTRING) => $this->circularString(),
                strtoupper(self::TYPE_COMPOUNDCURVE) => $this->compoundCurve(),
                strtoupper(self::TYPE_CURVEPOLYGON) => $this->curvePolygon(),
                strtoupper(self::TYPE_MULTICURVE) => $this->multiCurve(),
                strtoupper(self::TYPE_MULTISURFACE) => $this->multiSurface(),
                strtoupper(self::TYPE_POLYHEDRALSURFACE) => $this->polyhedralSurface(),
                default => throw new UnexpectedValueException(sprintf('Unsupported WKB type %s %d (%X)"', $typeName, $this->type, $this->type)),
            };

            return [
                'type' => $typeName,
                'srid' => $srid,
                'value' => $value,
                'dimension' => $this->getDimensionType($this->dimensions),
            ];
        } catch (ExceptionInterface $e) {
            throw new $e($e->getMessage().' at byte '.$this->reader->getLastPosition(), $e->getCode(), $e);
        }
    }

    /**
     * @param int $count
     *
     * @return (float|int)[][][]
     *
     * @throws UnexpectedValueException
     */
    private function readLinearRings($count): array
    {
        $rings = [];

        for ($i = 0; $i < $count; ++$i) {
            $rings[] = $this->readPoints($this->readCount());
        }

        return $rings;
    }

    /**
     * @return (float|int)[][]
     *
     * @throws UnexpectedValueException
     */
    private function readPoints(int $count): array
    {
        $points = [];

        for ($i = 0; $i < $count; ++$i) {
            $points[] = $this->point();
        }

        return $points;
    }

    /**
     * Parse SRID value.
     *
     * @throws UnexpectedValueException
     */
    private function readSrid(): int
    {
        $srid = $this->reader->readLong();
        if (!is_int($srid)) {
            throw new UnexpectedValueException('Invalid SRID value');
        }

        return $srid;
    }

    /**
     * Parse data type.
     *
     * @throws UnexpectedValueException
     */
    private function readType(): int
    {
        $type = $this->reader->readLong();

        if (!is_int($type)) {
            throw new UnexpectedValueException('Invalid type value');
        }

        return $type;
    }
}
