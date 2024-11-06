<?php

declare(strict_types=1);

namespace ArchiPro\EventDispatcher;

use function Amp\async;

use Amp\Pipeline\Queue;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

use Revolt\EventLoop;

/**
 * Asynchronous implementation of PSR-14 EventDispatcherInterface using Revolt and AMPHP.
 *
 * This dispatcher schedules event listeners to be executed asynchronously using the Revolt event loop.
 * The dispatch method returns immediately without waiting for listeners to complete.
 */
class AsyncEventDispatcher implements EventDispatcherInterface
{
    private ListenerProviderInterface $listenerProvider;

    /**
     * @param ListenerProviderInterface $listenerProvider The provider of event listeners
     */
    public function __construct(
        ListenerProviderInterface $listenerProvider
    ) {
        $this->listenerProvider = $listenerProvider;
    }

    /**
     * Dispatches an event to all registered listeners asynchronously.
     *
     * Each listener is scheduled in the event loop and executed asynchronously.
     * The method returns immediately without waiting for listeners to complete.
     * If the event implements StoppableEventInterface, propagation can be stopped
     * to prevent subsequent listeners from being scheduled.
     *
     * @param object $event The event to dispatch
     * @return object The dispatched event
     */
    public function dispatch(object $event): object
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        if ($event instanceof StoppableEventInterface) {
            return $this->dispatchStoppableEvent($event, $listeners);
        }

        return $this->dispatchNonStoppableEvent($event, $listeners);
    }

    /**
     * Dispatches a stoppable event to listeners asynchronously.
     * Uses a queue to handle propagation stopping.
     *
     * @param StoppableEventInterface $event
     * @param iterable<callable> $listeners
     * @return StoppableEventInterface
     */
    private function dispatchStoppableEvent(StoppableEventInterface $event, iterable $listeners): StoppableEventInterface
    {
        async(function () use ($event, $listeners): void {
            foreach ($listeners as $listener) {
                $listener($event);
                if ($event->isPropagationStopped()) {
                    break;
                }
            }
        });

        return $event;
    }

    /**
     * Dispatches a non-stoppable event to listeners asynchronously.
     * Simply queues all listeners in the event loop.
     *
     * Because we don't need to worry about stopping propagation, we can simply
     * queue all listeners in the event loop and let them run whenever in any order.
     *
     * @param object $event
     * @param iterable<callable> $listeners
     * @return object
     */
    private function dispatchNonStoppableEvent(object $event, iterable $listeners): object
    {
        foreach ($listeners as $listener) {
            EventLoop::queue(function () use ($event, $listener) {
                $listener($event);
            });
        }

        return $event;
    }

}
