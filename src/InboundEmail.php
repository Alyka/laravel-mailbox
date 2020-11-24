<?php

namespace BeyondCode\Mailbox;

use Carbon\Carbon;
use EmailReplyParser\EmailReplyParser;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use ZBateson\MailMimeParser\Message as MimeMessage;
use ZBateson\MailMimeParser\Message\Part\MessagePart;
use App\Library\Mailbox\Concerns\InboundEmail as InboundEmailSupport;

class InboundEmail extends \Model
{
	use InboundEmailSupport;
	
    protected $table = 'mailbox_inbound_emails';

    /** @var MimeMessage */
    protected $mimeMessage;

    protected $fillable = [
        'message',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->message_id = $model->id();
        });
    }

    public static function fromMessage($message)
    {
        return new static([
            'message' => $message,
        ]);
    }

    public function id(): string
    {
        return $this->getMessage()->getHeaderValue('Message-Id', Str::random());
    }

    public function date(): Carbon
    {
        return Carbon::make($this->getMessage()->getHeaderValue('Date'));
    }

    public function text(): ?string
    {
        return $this->getMessage()->getTextContent();
    }

    public function visibleText(): ?string
    {
        return EmailReplyParser::parseReply($this->text());
    }

    public function html(): ?string
    {
        return $this->getMessage()->getHtmlContent();
    }

    public function headerValue($headerName): ?string
    {
        return $this->getMessage()->getHeaderValue($headerName, null);
    }

    public function subject(): ?string
    {
        return $this->getMessage()->getHeaderValue('Subject');
    }

    public function from(): string
    {
        $from = $this->getMessage()->getHeader('From');

        if ($from instanceof AddressHeader) {
            return $from->getEmail();
        }

        return '';
    }

    public function fromName(): string
    {
        $from = $this->getMessage()->getHeader('From');

        if ($from instanceof AddressHeader) {
            return $from->getPersonName();
        }

        return '';
    }

    /**
     * @return AddressPart[]
     */
    public function to(): array
    {
        return $this->convertAddressHeader($this->getMessage()->getHeader('To'));
    }

    /**
     * @return AddressPart[]
     */
    public function cc(): array
    {
        return $this->convertAddressHeader($this->getMessage()->getHeader('Cc'));
    }

    /**
     * @return AddressPart[]
     */
    public function bcc(): array
    {
        return $this->convertAddressHeader($this->getMessage()->getHeader('Bcc'));
    }

    protected function convertAddressHeader($header): array
    {
        if ($header instanceof AddressHeader) {
            return Collection::make($header->getAddresses())->toArray();
        }

        return [];
    }

    /**
     * @return MessagePart[]
     */
    public function attachments()
    {
        return $this->getMessage()->getAllAttachmentParts();
    }

    public function getMessage(): MimeMessage
    {
        $this->mimeMessage = $this->mimeMessage ?: MimeMessage::from($this->message);

        return $this->mimeMessage;
    }

    public function reply(Mailable $mailable)
    {
        if ($mailable instanceof \Illuminate\Mail\Mailable) {
            $mailable->withSwiftMessage(function (\Swift_Message $message) {
                $message->getHeaders()->addIdHeader('In-Reply-To', $this->id());
            });
        }

        return Mail::to($this->from())->send($mailable);
    }

    public function forward($recipients)
    {
        return Mail::send([], [], function ($message) use ($recipients) {
            $message->to($recipients)
                ->subject($this->subject())
                ->setBody($this->body(), $this->getMessage()->getContentType());
        });
    }

    public function body(): ?string
    {
        return $this->isHtml() ? $this->html() : $this->text();
    }

    public function isHtml(): bool
    {
        return ! empty($this->html());
    }

    public function isText(): bool
    {
        return ! empty($this->text());
    }

    public function isValid(): bool
    {
        return $this->from() !== '' && ($this->isText() || $this->isHtml());
    }
}
