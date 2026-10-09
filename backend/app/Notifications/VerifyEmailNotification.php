<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued version of the stock notification. The signed link hits the API
 * route "verification.verify", which verifies and redirects to the SPA.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
