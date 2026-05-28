<?php

declare(strict_types=1);

namespace ICMS\Domain\ValueObjects;

use ICMS\Domain\Exceptions\DomainException;

final class CaseStatus
{
    public const OPEN = 'open';
    public const UNDER_REVIEW = 'under_review';
    public const RECOMMENDATION_DRAFTED = 'recommendation_drafted';
    public const RESOLVED = 'resolved';
    public const ARCHIVED = 'archived';

    private const VALID = [
        self::OPEN,
        self::UNDER_REVIEW,
        self::RECOMMENDATION_DRAFTED,
        self::RESOLVED,
        self::ARCHIVED,
    ];

    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        self::OPEN                      => [self::UNDER_REVIEW],
        self::UNDER_REVIEW              => [self::RECOMMENDATION_DRAFTED],
        self::RECOMMENDATION_DRAFTED    => [self::RESOLVED],
        self::RESOLVED                  => [self::ARCHIVED],
        self::ARCHIVED                  => [],
    ];

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID, true)) {
            throw new DomainException(sprintf('Invalid case status "%s".', $value));
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value], true);
    }

    public function transitionTo(self $next): self
    {
        if (!$this->canTransitionTo($next)) {
            throw new DomainException(sprintf(
                'Invalid status transition from "%s" to "%s".',
                $this->value,
                $next->value()
            ));
        }

        return $next;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
