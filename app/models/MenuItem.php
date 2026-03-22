<?php

namespace Model;

class MenuItem
{
    use Model;

    protected $table = 'menu_items';

    protected $allowedColumns = [
        'name',
        'description',
        'price_pence',
        'category',
        'image_path',
        'display_order',
        'is_available',
    ];
}
