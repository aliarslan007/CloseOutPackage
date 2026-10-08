<?php

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Policies\EntityFeedbackPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * The retired second feedback engine (CustomerFeedback / "System B") had its own
 * rounds, notes, policies, routes and UI, and duplicated every rule. These tests
 * are the tripwire: if any of it comes back, or if the surviving engine stops
 * failing closed, this file is the one that goes red.
 */
it('has no second feedback engine left in the codebase', function (): void {
    $retired = base_path('app/Domains/CustomerFeedback');

    expect(is_dir($retired))->toBeFalse('The retired CustomerFeedback domain must stay deleted.');

    $offenders = [];
    $paths = [
        base_path('app'),
        base_path('routes'),
        base_path('resources/js'),
        base_path('tests'),
    ];

    foreach ($paths as $path) {
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'ts', 'tsx'], true)) {
                continue;
            }

            // This file names the retired system in order to forbid it.
            if ($file->getPathname() === __FILE__) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            // CopFeedback* is unambiguous. The bare "CustomerFeedback" token is
            // too: the engine legitimately owns HasCustomerFeedback and
            // CustomerFeedbackSubmittedNotification, so match the namespace
            // instead of the class-name fragment.
            if (str_contains($contents, 'CopFeedback') || str_contains($contents, 'Domains\\CustomerFeedback')) {
                $offenders[] = $file->getPathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('denies every feedback ability when no user is authenticated', function (): void {
    $abilities = [
        'open', 'addNote', 'resolveNote', 'deleteNote',
        'forwardTechnician', 'resolveAll', 'submit', 'accept',
    ];

    foreach ($abilities as $ability) {
        expect(Gate::forUser(null)->allows($ability, [EntityFeedbackRound::class]))
            ->toBeFalse("ability '{$ability}' must fail closed without a user");
    }
});

it('never passes a dom event into a request payload', function (): void {
    // Inertia has no Event guard, so a handler wired straight to onClick hands it
    // a cyclic MouseEvent to serialise, and the page dies with
    // "Maximum call stack size exceeded". Wiring a handler bare is only safe
    // while it accepts no parameters: a zero-arg callback ignores the event,
    // anything else will try to serialise it.
    $offenders = [];
    $dir = base_path('resources/js/features/entity-feedback');

    foreach (glob($dir.'/components/*.tsx') ?: [] as $file) {
        $contents = (string) file_get_contents($file);

        if (preg_match_all('/on(?:Click|Submit)=\{(on[A-Z]\w*)\}/', $contents, $matches) === 0) {
            continue;
        }

        foreach (array_unique($matches[1]) as $handler) {
            // Find the prop or local declaration and read its parameter list.
            $declaration = preg_match(
                '/'.preg_quote($handler, '/').'\??\s*:\s*\(?([^)]*)\)?/',
                $contents,
                $params
            );

            if ($declaration === 1 && trim($params[1]) !== '') {
                $offenders[] = basename($file).': '.$handler;
            }
        }

    }

    expect($offenders)->toBe([]);
});

it('resolves the policy from the container', function (): void {
    expect(Gate::getPolicyFor(EntityFeedbackRound::class))
        ->toBeInstanceOf(EntityFeedbackPolicy::class);
});
