<?php

declare(strict_types=1);

namespace ArchiPro\EventDispatcher\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class for events that implements PSR-14 StoppableEventInterface.
 *
 * Provides basic functionality for stopping event propagation. Events that need
 * propagation control should extend this class.
 */
abstract class AbstractEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    /**
     * Checks if event propagation should be stopped.
     *
     * @return bool True if propagation should be stopped, false otherwise
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to subsequent listeners.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
