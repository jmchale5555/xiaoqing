<?php

namespace Tests\Unit\Core;

use Core\Request;
use Tests\TestCase;

class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    public function testGetPostAndAllReadFromSuperglobals(): void
    {
        $_GET['page'] = '2';
        $_POST['title'] = 'Hello';
        $_REQUEST = ['page' => '2', 'title' => 'Hello'];

        $request = new Request();

        $this->assertSame('2', $request->get('page'));
        $this->assertSame('Hello', $request->post('title'));
        $this->assertSame(['page' => '2', 'title' => 'Hello'], $request->all());
    }

    public function testJsonReturnsEmptyArrayWhenInputIsMissing(): void
    {
        $request = new Request();

        $this->assertSame([], $request->json());
    }
}
