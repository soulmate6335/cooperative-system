<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Payment verification status notification. Used both for loan repayments
 * (which carry a loan number) and for general membership payments.
 */
class PaymentVerified extends CooperativeNotification
{
    public function __construct(
        public readonly string $purpose,
        public readonly int $amountMinor,
        public readonly string $referenceNumber,
        public readonly string $paymentId,
        public readonly ?string $loanNumber = null,
    ) {}

    public static function typeKey(): string
    {
        return 'payment.verified';
    }

    public function title(): string
    {
        return 'Payment verified';
    }

    public function message(): string
    {
        $amount = $this->formatMinor($this->amountMinor);

        if ($this->loanNumber !== null) {
            return "Your repayment of {$amount} towards loan {$this->loanNumber} has been verified.";
        }

        return "Your {$this->purpose} payment of {$amount} has been verified.";
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload('payment', $this->paymentId, $this->referenceNumber);
    }
}
