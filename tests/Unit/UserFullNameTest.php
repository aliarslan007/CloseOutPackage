<?php

use App\Domains\Users\Models\User;

it('composes a clean first and last name', function (): void {
    expect(User::fullName('John', 'Lime'))->toBe('John Lime');
});

it('does not duplicate the last name when the first name already ends with it', function (): void {
    expect(User::fullName('John Lime', 'Lime'))->toBe('John Lime');
});

it('fully dedupes a name with multiple appended last names', function (): void {
    expect(User::fullName('John Lime Lime Lime', 'Lime'))->toBe('John Lime');
});

it('keeps multi-word first names intact when they do not end with the last name', function (): void {
    expect(User::fullName('John A', 'Lime'))->toBe('John A Lime');
});

it('collapses a polluted last-name field with repeated copies', function (): void {
    expect(User::fullName('John', 'Lime Lime'))->toBe('John Lime');
});

it('keeps compound surnames intact', function (): void {
    expect(User::fullName('John', 'van der Berg'))->toBe('John van der Berg');
});

it('handles empty parts', function (): void {
    expect(User::fullName('', ''))->toBe('')
        ->and(User::fullName('John', ''))->toBe('John')
        ->and(User::fullName('', 'Lime'))->toBe('Lime');
});
