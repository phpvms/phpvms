<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Messages\MailMessage;

trait MailChannel
{
    protected $mailSubject;

    protected $mailTemplate;

    protected $mailTemplateArgs;

    /**
     * Set the arguments for the toMail() method
     *
     * @param string $subject  Email subject
     * @param string $template Markdown template to use
     * @param array  $args     Arguments to pass to the template
     */
    public function setMailable(string $subject, string $template, array $args): void
    {
        $this->mailSubject = $subject;
        $this->mailTemplate = $template;
        $this->mailTemplateArgs = $args;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed                                          $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->from(config('mail.from.address', 'no-reply@phpvms.net'), config('mail.from.name'))
            ->subject($this->mailSubject)
            ->markdown($this->mailTemplate, $this->mailTemplateArgs);
    }
}
