<?php

namespace App\Support;

class Event
{
    const PUSH = 10;

    const BRANCH_CREATED = 20;

    const BRANCH_DELETED = 30;

    const TAG_CREATED = 40;

    const TAG_DELETED = 50;

    /**
     * The events that can be selected when configuring a workflow.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::PUSH, 'label' => 'Push'],
        ];
    }

    /**
     * Human-readable label for a stored event value.
     */
    public static function label(?int $event): string
    {
        foreach (self::options() as $option) {
            if ($option['value'] === $event) {
                return $option['label'];
            }
        }

        return 'Unknown';
    }

    /**
     * @param  string  $event
     * @return int|null
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
