<?php

namespace Vatsim\Osticket\Spaces;

use HashContext;

class FileHasher
{
    /**
     * The hash context.
     */
    private HashContext $ctx;

    /**
     * Whether or not the hasher has received data.
     */
    private bool $hasData = false;

    /**
     * The final hash.
     */
    private ?string $final = null;

    /**
     * Create a new hasher instance.
     */
    public function __construct()
    {
        $this->ctx = hash_init('md5');
    }

    /**
     * Update the hash with new data.
     */
    public function update(string $data): void
    {
        $this->hasData = true;

        hash_update($this->ctx, $data);
    }

    /**
     * Update the hash with data from a file.
     */
    public function updateFile(string $filename): void
    {
        $this->hasData = true;

        hash_update_file($this->ctx, $filename);
    }

    /**
     * Get the final hash.
     */
    public function digest(): string
    {
        if (! $this->hasData) {
            return '';
        }

        if ($this->final === null) {
            $this->final = hash_final($this->ctx);
        }

        return $this->final;
    }
}
