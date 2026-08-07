<?php

namespace App\Support;

use App\Models\ProjectVariable;
use Illuminate\Support\Collection;

/**
 * Replaces variable values with [masked] in text on its way into storage.
 *
 * Deployment output is kept for a hundred days and rendered in the UI, so a
 * variable that a build script happens to echo would otherwise be readable long
 * after the fact by anyone who can see the deployment -- the value being
 * encrypted in the database is no help once it has been printed.
 *
 * Very short values are skipped: masking a two-character value would riddle the
 * log with [masked] wherever those characters occur and destroy far more than
 * it protects. GitLab draws the same line (it requires 8 characters).
 */
class SecretMasker
{
    private const MIN_LENGTH = 5;

    /**
     * @param  array<int, string>  $values
     */
    public function __construct(private array $values = []) {}

    /**
     * @param  Collection<int, ProjectVariable>|iterable<ProjectVariable>  $variables
     */
    public static function for(iterable $variables): self
    {
        $values = [];

        foreach ($variables as $variable) {
            if (! $variable->masked) {
                continue;
            }

            $value = (string) $variable->value;

            if (mb_strlen($value) >= self::MIN_LENGTH) {
                $values[] = $value;
            }
        }

        // Longest first, so a value that contains another is masked whole
        // rather than leaving its unique remainder visible.
        usort($values, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return new self($values);
    }

    public function mask(string $text): string
    {
        if ($this->values === []) {
            return $text;
        }

        return str_replace($this->values, '[masked]', $text);
    }
}
