<?php

declare(strict_types=1);

namespace ArchiPro\EventDispatcher\Tests\Fixture;

use ArchiPro\EventDispatcher\Event\AbstractEvent;

/**
 * Simple event implementation for testing purposes.
 *
 * Contains a single data property that can be used to verify event handling
 * in test cases.
 */
class TestEvent extends AbstractEvent
{
    /**
     * @param string $data Test data to be carried by the event
     */
    public function __construct(
        public readonly string $data
    ) {}
} 