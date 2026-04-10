<?php

namespace App\Exceptions\Billing;

/**
 * Apple subscription lineage (original_transaction_id) is owned by another internal user.
 */
class OwnershipConflictException extends \RuntimeException
{
    public function __construct(
        public readonly int $ownerUserId
    ) {
        parent::__construct('Apple subscription already linked to another account');
    }
}
