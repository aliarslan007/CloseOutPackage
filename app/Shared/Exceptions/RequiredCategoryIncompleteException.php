<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

final class RequiredCategoryIncompleteException extends WorkflowException
{
    /** @param list<string> $categoryNames */
    public function __construct(array $categoryNames)
    {
        $list = implode(', ', array_map(fn (string $name): string => "\"{$name}\"", $categoryNames));

        parent::__construct("Add at least one photo to the required categories before submitting: {$list}.");
    }
}
