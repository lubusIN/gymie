<?php

namespace App\Contracts;

/**
 * Sequence / number generation abstraction (e.g. Invoice / Member numbers).
 *
 * OSS default uses JSON-backed settings. Other installations can override this
 * binding to generate sequences safely (for example, using row locks).
 */
interface SequenceRepository
{
    /**
     * Reserve and return the next number in the sequence.
     *
     * @param  class-string  $modelClass
     */
    public function generate(
        string $type,
        string $modelClass,
        ?string $dateString = null,
        ?string $modelColumn = 'number',
    ): string;

    /**
     * Persist the last used sequence number (if the UI allows manual override).
     */
    public function update(
        string $type,
        string $newNumber,
        ?string $date = null,
    ): void;
}
