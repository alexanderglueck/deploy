<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    #[Test]
    public function a_user_has_a_name()
    {
        $user = new User;
        $user->name = 'John Doe';

        $this->assertEquals('John Doe', $user->name);
    }
}
