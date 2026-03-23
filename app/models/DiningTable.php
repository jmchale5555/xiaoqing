<?php

namespace Model;

class DiningTable
{
    use Model;

    protected $table = 'tables';

    protected $allowedColumns = [
        'name',
        'seats',
        'is_active',
        'display_order',
    ];
}
