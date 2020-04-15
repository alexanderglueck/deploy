<?php

namespace App;

class Event
{
    const PUSH = 10;
    const BRANCH_CREATED = 20;
    CONST BRANCH_DELETED = 30;
    const TAG_CREATED = 40;
    CONST TAG_DELETED = 50;

    /**
     * @param string $event
     * @return int
     */
    public static function getEvent($event)
    {
        switch ($event) {
            case 'push':
                return self::PUSH;
            case 'create':
                return self::BRANCH_CREATED;
            case 'delete':
                return self::BRANCH_DELETED;
        }
    }
}
