<?php

namespace Tests\Unit;

use App\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /** @test */
    public function a_user_has_a_name()
    {
        $user = new User();
        $user->name = 'John Doe';

        $this->assertEquals('John Doe', $user->name);
    }
}
