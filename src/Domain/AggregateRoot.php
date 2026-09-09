<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Domain;

/**
 * Base class for generated aggregate roots. Framework-agnostic by design —
 * the Domain layer must never depend on Illuminate.
 */
abstract class AggregateRoot
{
    /** @var list<object> */
    private array $recordedEvents = [];

    protected function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
