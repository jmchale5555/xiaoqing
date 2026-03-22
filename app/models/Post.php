<?php

namespace Model;

class Post
{
    use Model;

    protected $table = 'posts';

    protected $allowedColumns = [
        'user_id',
        'title',
        'body',
        'slug',
        'is_published',
        'published_at',
    ];
}
