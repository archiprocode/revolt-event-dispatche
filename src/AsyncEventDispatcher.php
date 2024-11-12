<?php

declare(strict_types=1);

namespace ArchiPro\EventDispatcher;

use function Amp\async;

use Amp\Cancellation;
use Amp\Future;

use function Amp\Future\awaitAll;

use Amp\NullCancellation;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Asynchronous implementation of PSR-14 EventDispatcherInterface using Revolt and AMPHP.
 *
 * This dispatcher schedules event listeners to be executed asynchronously using the Revolt event loop.
 * The dispatch method returns a Future that resolves when all listeners complete.
 */
class AsyncEventDispatcher implements EventDispatcherInterface
{
    /**
     * @param ListenerProviderInterface $listenerProvider The provider of event listeners
     */
    public function __construct(
        private readonly ListenerProviderInterface $listenerProvider
    ) {
    }

    /**
     * Dispatches an event to all registered listeners asynchronously.
     *
     * Each listener is scheduled in the event loop and executed asynchronously.
     * Returns a Future that resolves with the event once all listeners complete.
     * If the event implements StoppableEventInterface, propagation can be stopped
     * to prevent subsequent listeners from being scheduled.
     *
     * @template T of object
     * @param T $event The event to dispatch
     * @return Future<T> Future that resolves with the dispatched event
     */
    public function dispatch(object $event, ?Cancellation $cancellation = null): Future
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        if ($event instanceof StoppableEventInterface) {
            return $this->dispatchStoppableEvent(
                $event,
                $listeners,
                $cancellation ?: new NullCancellation()
            );
        }

        return $this->dispatchNonStoppableEvent(
            $event,
            $listeners,
            $cancellation ?: new NullCancellation()
        );
    }

    /**
     * Dispatches a stoppable event to listeners asynchronously.
     * Uses a queue to handle propagation stopping.
     *
     * @template T of StoppableEventInterface
     * @param T $event
     * @param iterable<callable> $listeners
     * @return Future<T>
     */
    private function dispatchStoppableEvent(
        StoppableEventInterface $event,
        iterable $listeners,
        Cancellation $cancellation
    ): Future {
        return async(function () use ($event, $listeners, $cancellation): StoppableEventInterface {
            // We'll process each listener in sequence so that if one decides to stop propagation,
            // we have chance to kill the following listeners.
            foreach ($listeners as $listener) {
                // We'll wrap our listener in a `async` call. Even if we want to block the next listener in the loop,
                // that doesn't mean we want to block other listeners outside this loop.
                $future = async(function () use ($event, $listener) {
                    $listener($event);
                });

                $future->await($cancellation);

                // If one of our listeners decides to stop propagation, we'll break out of the loop.
                if ($event->isPropagationStopped()) {
                    break;
                }
            }
            return $event;
        });
    }

    /**
     * Dispatches a non-stoppable event to listeners asynchronously.
     * Simply queues all listeners in the event loop.
     *
     * Because we don't need to worry about stopping propagation, we can simply
     * queue all listeners in the event loop and let them run whenever in any order.
     *
     * @template T of object
     * @param T $event
     * @param iterable<callable> $listeners
     * @return Future<T>
     */
    private function dispatchNonStoppableEvent(
        object $event,
        iterable $listeners,
        Cancellation $cancellation
    ): Future {
        return async(function () use ($event, $listeners, $cancellation): object {
            $futures = [];
            foreach ($listeners as $listener) {
                $futures[] = async(function () use ($event, $listener) {
                    $listener($event);
                });
            }

            // Wait for all listeners to complete
            awaitAll($futures, $cancellation);

            return $event;
        });
    }

}
