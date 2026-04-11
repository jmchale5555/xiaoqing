<?php

namespace Model;

class Booking
{
    use Model;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SEATED = 'seated';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    protected $table = 'bookings';

    protected $allowedColumns = [
        'guest_name',
        'guest_phone',
        'guest_email',
        'party_size',
        'booking_start',
        'booking_end',
        'status',
        'table_id',
        'notes',
    ];

    public static function validStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_SEATED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ];
    }
}
