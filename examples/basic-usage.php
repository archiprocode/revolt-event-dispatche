<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\TimeoutCancellation;
use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\Event\AbstractStoppableEvent;
use ArchiPro\EventDispatcher\ListenerProvider;
use Revolt\EventLoop;

// Create a custom event
class UserCreatedEvent extends AbstractStoppableEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email
    ) {
    }
}

// Create the listener provider and register listeners
$listenerProvider = new ListenerProvider();

$listenerProvider->addListener(UserCreatedEvent::class, function (UserCreatedEvent $event) {
    // Simulate async operation
    EventLoop::delay(
        1,
        function () use ($event) {
            echo "Sending welcome email to {$event->email}\n";
        }
    );
});

$listenerProvider->addListener(UserCreatedEvent::class, function (UserCreatedEvent $event) {
    // Simulate async operation
    EventLoop::delay(
        0.5,
        function () use ($event) {
            echo "Logging user creation: {$event->userId}\n";
        }
    );
});

// Create the event dispatcher
$dispatcher = new AsyncEventDispatcher($listenerProvider);

// Dispatch an event
$event = new UserCreatedEvent('123', 'user@example.com');
$dispatcher->dispatch($event);

// Run the event loop to process all events
EventLoop::run();

// Wait for the event to finish right away
$event = new UserCreatedEvent('456', 'user@example.com');
$future = $dispatcher->dispatch($event);
$updatedEvent = $future->await();

// Make an event cancellable
$event = new UserCreatedEvent('789', 'user@example.com');
$future = $dispatcher->dispatch($event, new TimeoutCancellation(30));
EventLoop::run();

// Set up logging for your dispatcher - all errors will be logged to PSR logger
$dispatcher = new AsyncEventDispatcher(
    $listenerProvider,
    function (Throwable $exception) {
        error_log($exception->getMessage());
    }
);
