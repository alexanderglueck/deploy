<?php

namespace Tests\Unit;

use App\Models\Team;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TeamTest extends TestCase
{
    #[Test]
    public function a_team_has_a_name()
    {
        $team = new Team;
        $team->name = 'John';

        $this->assertEquals('John', $team->name);
    }
}
