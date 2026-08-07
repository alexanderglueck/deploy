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
     * Trailing bytes of the stream that could still be the start of a value.
     */
    private string $carry = '';

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

    /**
     * Mask a chunk of a stream, holding back the tail that could still turn out
     * to be the start of a value.
     *
     * Output arrives in arbitrary chunks, so a secret can straddle two of them
     * and would slip past a per-chunk replace. Enough trailing bytes to cover
     * the longest value are carried over to the next call, and released by
     * flush() when the stream ends.
     */
    public function maskChunk(string $chunk): string
    {
        if ($this->values === []) {
            return $chunk;
        }

        // Mask the combined buffer BEFORE deciding what to release: a value
        // straddling the boundary is only whole here, and masking just the
        // part about to be emitted would never see it.
        $pending = $this->mask($this->carry.$chunk);
        $hold = $this->longestValue() - 1;

        if ($hold < 1 || strlen($pending) <= $hold) {
            $this->carry = $pending;

            return '';
        }

        $this->carry = substr($pending, -$hold);

        return substr($pending, 0, -$hold);
    }

    /**
     * Release whatever the stream ended on.
     */
    public function flush(): string
    {
        $remaining = $this->carry;
        $this->carry = '';

        return $remaining === '' ? '' : $this->mask($remaining);
    }

    private function longestValue(): int
    {
        return $this->values === [] ? 0 : max(array_map('strlen', $this->values));
    }
}
