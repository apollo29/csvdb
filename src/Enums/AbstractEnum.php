<?php

namespace CSVDB\Enums;

abstract class AbstractEnum {

    /**
     * Creates a new value of some type
     *
     * @param mixed $value
     *
     * @throws \UnexpectedValueException if incompatible type is given.
     * @throws \ReflectionException
     */
    public function __construct($value) {
        if (!$this->isValid($value)) {
            throw new \UnexpectedValueException("Value '$value' is not part of the enum " . get_called_class());
        }
        $this->value = $value;
    }

    /**
     * @throws \ReflectionException
     */
    public static function getConstants(): array
    {
        $class = get_called_class();
        $reflection = new \ReflectionClass($class);

        return $reflection->getConstants();
    }

    /**
     * Check if enum value is valid
     *
     * @param $value
     *
     * @return bool
     * @throws \ReflectionException
     */
    public static function isValid($value): bool
    {
        return in_array($value, static::getConstants(), true);
    }
}
