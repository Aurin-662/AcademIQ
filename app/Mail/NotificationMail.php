<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** Plain-text notification email. Never include credential data, proofs or participant remarks. */
class NotificationMail extends Mailable
{
    public function __construct(public string $heading, public string $body, public ?string $link = null) {}

    public function build()
    {
        return $this->subject($this->heading.' · AcademiQ')->text('mail.notification');
    }
}
