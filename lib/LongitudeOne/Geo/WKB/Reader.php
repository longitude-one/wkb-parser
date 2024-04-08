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

use LongitudeOne\Geo\WKB\Exception\InvalidArgumentException;
use LongitudeOne\Geo\WKB\Exception\RangeException;
use LongitudeOne\Geo\WKB\Exception\UnexpectedValueException;

/**
 * Reader for spatial WKB values.
 */
class Reader
{
    public const WKB_NDR = 1;
    public const WKB_XDR = 0;

    /**
     * Byte order for current machine.
     */
    private static ?int $machineByteOrder = null;

    /**
     * Byte order for current input.
     */
    private ?int $byteOrder = null;

    private ?string $input = null;

    private int $position = 0;

    private int $previous = 0;

    /**
     * @param string $input
     *
     * @throws UnexpectedValueException
     */
    public function __construct($input = null)
    {
        if (null !== $input) {
            $this->load($input);
        }
    }

    public function getCurrentPosition(): int
    {
        return $this->position;
    }

    public function getLastPosition(): int
    {
        return $this->position - $this->previous;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function load(string $input): void
    {
        $this->position = 0;
        $this->previous = 0;

        if (ord($input) < 32) {
            $this->input = $input;

            return;
        }

        $position = stripos($input, 'x');

        if (false !== $position) {
            $input = substr($input, $position + 1);
        }

        $this->input = pack('H*', $input);
    }

    /**
     * @throws RangeException
     * @throws UnexpectedValueException
     */
    public function readByteOrder(): int
    {
        /** @var int $byteOrder */
        $byteOrder = $this->unpackInput('C');

        $this->previous = 1;
        $this->position += $this->previous;

        if ($byteOrder >> 1) {
            throw new UnexpectedValueException('Invalid byte order "'.$byteOrder.'"');
        }

        return $this->byteOrder = $byteOrder;
    }

    /**
     * @throws UnexpectedValueException
     * @throws RangeException
     *
     * @deprecated use readFloat()
     */
    public function readDouble(): float
    {
        trigger_error(static::class.': Method readDouble is deprecated, use readFloat instead.', E_USER_DEPRECATED);

        return $this->readFloat();
    }

    /**
     * @return float[]
     *
     * @throws RangeException
     * @throws UnexpectedValueException
     *
     * @deprecated use readFloats()
     */
    public function readDoubles(int $count): array
    {
        trigger_error(static::class.': Method readDoubles is deprecated, use readFloats instead.', E_USER_DEPRECATED);

        return $this->readFloats($count);
    }

    /**
     * @throws RangeException
     * @throws UnexpectedValueException
     */
    public function readFloat(): float
    {
        /** @var float $double */
        $double = $this->unpackInput('d');

        if ($this->getMachineByteOrder() !== $this->getByteOrder()) {
            /** @var array{value: float} $unpacked */
            $unpacked = unpack('dvalue', strrev(pack('d', $double)));
            $double = $unpacked['value'];
        }

        $this->previous = 8;
        $this->position += $this->previous;

        return $double;
    }

    /**
     * @return float[]
     *
     * @throws RangeException
     * @throws UnexpectedValueException
     */
    public function readFloats(int $count): array
    {
        $floats = [];

        for ($i = 0; $i < $count; ++$i) {
            $float = $this->readFloat();

            if (!is_nan($float)) {
                $floats[] = $float;
            }
        }

        return $floats;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function readLong(): float|int
    {
        $format = self::WKB_NDR === $this->getByteOrder() ? 'V' : 'N';
        $value = $this->unpackInput($format);
        $this->previous = 4;
        $this->position += $this->previous;

        return $value;
    }

    /**
     * @return int
     *
     * @throws UnexpectedValueException
     */
    private function getByteOrder()
    {
        if (null === $this->byteOrder) {
            throw new UnexpectedValueException('Invalid byte order "unset"');
        }

        return $this->byteOrder;
    }

    private function getInvalidArgumentException(int $errorNumber, string $errorMessage): InvalidArgumentException
    {
        $message = sprintf('%s: Error number %d: %s.', static::class, $errorNumber, $errorMessage);

        return new InvalidArgumentException($message);
    }

    /**
     * @return int return the Byte order for current machine
     */
    private function getMachineByteOrder(): int
    {
        if (null === self::$machineByteOrder) {
            $result = unpack('S', "\x01\x00");

            if (false === $result) {
                $message = sprintf(
                    '%s: Unable to determine the current machine Byte order. Unpack failed.',
                    static::class
                );
                throw new InvalidArgumentException($message);
            }

            self::$machineByteOrder = 1 === $result[1] ? self::WKB_NDR : self::WKB_XDR;
        }

        return self::$machineByteOrder;
    }

    private function onWarning(int $errorNumber, string $errorMessage): void
    {
        throw $this->getInvalidArgumentException($errorNumber, $errorMessage);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function unpackInput(string $format): float|int
    {
        if (null === $this->input) {
            throw $this->getInvalidArgumentException(1, 'No input data to read. Input is null.');
        }

        set_error_handler([$this, 'onWarning'], E_WARNING);
        $result = unpack($format.'result/a*input', $this->input);
        restore_error_handler();

        if (false === $result) {
            // this code is certainly unreachable
            $message = sprintf(
                '%s: Unpack failed, the native PHP `unpack` function is returning false without triggering a warning',
                static::class
            );
            throw new InvalidArgumentException($message);
        }

        $this->input = $result['input'];

        return $result['result'];
    }
}
