<?php

namespace Model;

class BookingEvent
{
    use Model;

    protected $table = 'booking_events';

    protected $allowedColumns = [
        'booking_id',
        'event_type',
        'actor_user_id',
        'from_value',
        'to_value',
        'meta_json',
        'created_at',
    ];
}
