<?php

namespace App\Enum;

enum CourseType: int
{
    case FREE = 1;
    case RENT = 2;
    case PAY = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::FREE => 'free',
            self::RENT => 'rent',
            self::PAY => 'pay'
        };
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
