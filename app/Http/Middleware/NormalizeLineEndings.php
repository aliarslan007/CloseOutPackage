<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Browsers submit textarea newlines as CRLF (HTML form-submission algorithm),
 * so the string the server measures is one character longer per line break than
 * the one the field's counter measured. A 3,000 character description with four
 * line breaks reached validation as 3,004 and was rejected while the user was
 * looking at "2999 / 3000" — the counter and the `max:` rule were both correct,
 * they were just counting different things.
 *
 * Folding CRLF back to LF at the boundary makes a `max:` rule mean "characters
 * as the user sees them" for every field, instead of being short by one per line
 * break. Done once here rather than in each FormRequest, because the same
 * off-by-N applies to every textarea with a length limit.
 */
final class NormalizeLineEndings
{
    /**
     * Never rewritten: altering a credential would change it, the same reason
     * Illuminate\Foundation\Http\Middleware\TrimStrings skips these.
     *
     * @var list<string>
     */
    private const EXCLUDED = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        /** @var array<string, mixed> $post */
        $post = $this->normalize($request->request->all());
        $request->request->replace($post);

        // Query strings carry the same free text in search boxes and prefilled
        // filters, so they get the same treatment.
        /** @var array<string, mixed> $query */
        $query = $this->normalize($request->query->all());
        $request->query->replace($query);

        return $next($request);
    }

    private function normalize(mixed $value, string $key = ''): mixed
    {
        if (is_string($value)) {
            return in_array($key, self::EXCLUDED, true) ? $value : str_replace(["\r\n", "\r"], "\n", $value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $childKey => $child) {
                $normalized[$childKey] = $this->normalize($child, (string) $childKey);
            }

            return $normalized;
        }

        // Uploads live in the files bag and are never strings here, so they
        // pass through untouched.
        return $value;
    }
}
