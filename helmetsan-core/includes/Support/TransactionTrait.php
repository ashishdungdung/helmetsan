<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Provides database transaction helper methods.
 */
trait TransactionTrait
{
    /**
     * Start a database transaction.
     */
    protected function startTransaction(): bool
    {
        global $wpdb;
        return (bool) $wpdb->query('START TRANSACTION');
    }

    /**
     * Commit the current transaction.
     */
    protected function commitTransaction(): void
    {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    /**
     * Roll back the current transaction.
     */
    protected function rollbackTransaction(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }
}
