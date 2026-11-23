<?php

namespace App\Tenancy;

use App\Models\Organization;
use RuntimeException;

/**
 * Holds the organization (tenant) for the current request or job.
 *
 * Registered as a *scoped* singleton, so it is reset between requests under
 * Octane and between queued jobs.
 */
class TenantContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    public function organization(): Organization
    {
        return $this->organization
            ?? throw new RuntimeException('No tenant has been resolved for this request.');
    }

    /**
     * Run a callback as a given tenant (queue jobs, console commands, tests),
     * restoring the previous tenant afterwards.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
