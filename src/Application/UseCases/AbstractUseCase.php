<?php

declare(strict_types=1);

namespace ICMS\Application\UseCases;

abstract class AbstractUseCase
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    abstract public function execute(array $input): array;
}
