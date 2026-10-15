<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING    = 'pending';
    case CONFIRMED  = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED    = 'shipped';
    case DELIVERED  = 'delivered';
    case CANCELLED  = 'cancelled';

    /**
     * Whether this status may transition to the given status.
     *
     * Terminal states (delivered, cancelled) accept no further transitions.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::PENDING    => in_array($to, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED  => in_array($to, [self::PROCESSING, self::CANCELLED], true),
            self::PROCESSING => in_array($to, [self::SHIPPED, self::CANCELLED], true),
            self::SHIPPED    => $to === self::DELIVERED,
            self::DELIVERED  => false,
            self::CANCELLED  => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELLED], true);
    }
}