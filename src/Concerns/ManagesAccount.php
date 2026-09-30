<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Account endpoints: monthly usage and balance.
 */
trait ManagesAccount
{
    /**
     * The current monthly usage for the authenticated key.
     *
     * @throws VoiceKitError
     */
    public function usage(): Result
    {
        return $this->get('/v1/usage');
    }

    /**
     * The current balance, plan and recent transactions.
     *
     * @throws VoiceKitError
     */
    public function billingBalance(): Result
    {
        return $this->get('/v1/billing/balance');
    }
}
