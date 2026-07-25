<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Cake\I18n\DateTime;

class Clock
{
    /**
     * Get the current date and time as a formatted string.
     */
    public function now(): string
    {
        return DateTime::now()->toDateTimeString();
    }
}
