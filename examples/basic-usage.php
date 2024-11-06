<?php

require __DIR__ . '/../vendor/autoload.php';

use ArchiPro\EventDispatcher\AsyncEventDispatcher;
use ArchiPro\EventDispatcher\Event\AbstractEvent;
use ArchiPro\EventDispatcher\ListenerProvider;
use Revolt\EventLoop;

// Create a custom event
class UserCreatedEvent extends AbstractEvent
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
    EventLoop::delay(1, fn () => null);
    echo "Sending welcome email to {$event->email}\n";
});

$listenerProvider->addListener(UserCreatedEvent::class, function (UserCreatedEvent $event) {
    // Simulate async operation
    EventLoop::delay(0.5, fn () => null);
    echo "Logging user creation: {$event->userId}\n";
});

// Create the event dispatcher
$dispatcher = new AsyncEventDispatcher($listenerProvider);

// Dispatch an event
$event = new UserCreatedEvent('123', 'user@example.com');
$dispatcher->dispatch($event);

// Run the event loop
EventLoop::run();
