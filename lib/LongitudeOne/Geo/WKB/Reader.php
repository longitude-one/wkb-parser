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
     * @var int
     */
    private static $machineByteOrder;

    /**
     * @var int
     */
    private $byteOrder;

    /**
     * @var string
     */
    private $input;

    /**
     * @var int
     */
    private $position;

    /**
     * @var int
     */
    private $previous;

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

    /**
     * @return int
     */
    public function getCurrentPosition()
    {
        return $this->position;
    }

    /**
     * @return int
     */
    public function getLastPosition()
    {
        return $this->position - $this->previous;
    }

    /**
     * @param string $input
     *
     * @throws UnexpectedValueException
     */
    public function load($input)
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
     * @return int
     *
     * @throws RangeException
     * @throws UnexpectedValueException
     */
    public function readByteOrder()
    {
        $byteOrder = $this->unpackInput('C');

        $this->previous = 1;
        $this->position += $this->previous;

        if ($byteOrder >> 1) {
            throw new UnexpectedValueException('Invalid byte order "'.$byteOrder.'"');
        }

        return $this->byteOrder = $byteOrder;
    }

    /**
     * @return float
     *
     * @throws UnexpectedValueException
     * @throws RangeException
     *
     * @deprecated use readFloat()
     */
    public function readDouble()
    {
        return $this->readFloat();
    }

    /**
     * @param int $count
     *
     * @return float[]
     *
     * @throws RangeException
     * @throws UnexpectedValueException
     *
     * @deprecated use readFloats()
     */
    public function readDoubles($count)
    {
        return $this->readFloats($count);
    }

    /**
     * @return float
     *
     * @throws RangeException
     * @throws UnexpectedValueException
     */
    public function readFloat()
    {
        $double = $this->unpackInput('d');

        if ($this->isMachineByteOrdered() !== $this->getByteOrder()) {
            $double = unpack('dvalue', strrev(pack('d', $double)));
            $double = $double['value'];
        }

        $this->previous = 8;
        $this->position += $this->previous;

        return $double;
    }

    /**
     * @param int $count
     *
     * @return float[]
     *
     * @throws RangeException
     * @throws UnexpectedValueException
     */
    public function readFloats($count)
    {
        $floats = [];

        for ($i = 0; $i < $count; ++$i) {
            $float = $this->readFloat();

            if (!is_null($float) && !is_nan($float)) {
                $floats[] = $float;
            }
        }

        return $floats;
    }

    /**
     * @return int
     *
     * @throws UnexpectedValueException
     */
    public function readLong()
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

    /**
     * @return bool
     */
    private function isMachineByteOrdered()
    {
        if (null === self::$machineByteOrder) {
            $result = unpack('S', "\x01\x00");

            self::$machineByteOrder = 1 === $result[1] ? self::WKB_NDR : self::WKB_XDR;
        }

        return self::$machineByteOrder;
    }

    private function onWarning(int $errorNumber, string $errorMessage): void
    {
        $message = sprintf('%s: Error number %d: %s', static::class, $errorNumber, $errorMessage);

        throw new InvalidArgumentException($message);
    }

    /**
     * @param string $format
     *
     * @throws InvalidArgumentException
     */
    private function unpackInput($format)
    {
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
