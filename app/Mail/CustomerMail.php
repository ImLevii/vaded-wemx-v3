<?php

namespace App\Mail;

use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The email instance.
     */
    public Email $email;

    private EmailTheme $emailTheme;

    /**
     * Create a new message instance.
     */
    public function __construct(Email $email)
    {
        $this->email = $email;
        $this->emailTheme = EmailTheme::resolve($email->theme);
        $this->theme = $this->emailTheme->cssView();

        // Set the default configuration for the mailer
        config([
            'app.name' => settings('app_name', 'My Application'),
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->email->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $body = implode("\n", $this->email->lines ?? []);
        $markdownTable = $this->markdownTable();
        $data = [
            'name' => $this->email->user?->username,
            'subject' => $this->email->subject,
            'body' => $body,
            'text' => $body.($markdownTable !== '' ? "\n\n".$markdownTable : ''),
            'markdownTable' => $markdownTable,
            'button' => ['text' => $this->email->button_text, 'url' => $this->email->button_url],
        ];
        if ($this->emailTheme->usesMarkdown()) {
            return new Content(markdown: $this->emailTheme->htmlView(), with: $data);
        }
        $data['body'] = Str::markdown($data['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return new Content(
            view: $this->emailTheme->htmlView(), text: $this->emailTheme->textView(), with: $data,
        );
    }

    protected function markdownRenderer(): Markdown
    {
        $paths = config('mail.markdown.paths', []);
        if ($path = $this->emailTheme->mailComponentsPath()) {
            array_unshift($paths, $path);
        }

        $renderer = parent::markdownRenderer();
        $renderer->loadComponentsFrom($paths);

        return $renderer;
    }

    private function markdownTable(): string
    {
        $table = $this->email->table;
        if (empty($table['columns'])) {
            return '';
        }
        $row = fn (array $cells): string => '| '.implode(' | ', array_map(
            fn (mixed $cell): string => str_replace(["\r", "\n", '|'], ['', ' ', '\\|'], e((string) $cell)), $cells,
        )).' |';

        return implode("\n", [
            $row($table['columns']), $row(array_fill(0, count($table['columns']), '---')),
            ...array_map($row, $table['rows'] ?? []),
        ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
