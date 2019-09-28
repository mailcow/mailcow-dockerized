<?php

declare(strict_types=1);

namespace Ddeboer\Imap\Message;

/**
 * A message part.
 */
interface PartInterface extends \RecursiveIterator
{
    public const TYPE_TEXT          = 'text';
    public const TYPE_MULTIPART     = 'multipart';
    public const TYPE_MESSAGE       = 'message';
    public const TYPE_APPLICATION   = 'application';
    public const TYPE_AUDIO         = 'audio';
    public const TYPE_IMAGE         = 'image';
    public const TYPE_VIDEO         = 'video';
    public const TYPE_MODEL         = 'model';
    public const TYPE_OTHER         = 'other';
    public const TYPE_UNKNOWN       = 'unknown';

    public const ENCODING_7BIT              = '7bit';
    public const ENCODING_8BIT              = '8bit';
    public const ENCODING_BINARY            = 'binary';
    public const ENCODING_BASE64            = 'base64';
    public const ENCODING_QUOTED_PRINTABLE  = 'quoted-printable';
    public const ENCODING_UNKNOWN           = 'unknown';

    public const SUBTYPE_PLAIN  = 'PLAIN';
    public const SUBTYPE_HTML   = 'HTML';
    public const SUBTYPE_RFC822 = 'RFC822';

    /**
     * Get message number (from headers).
     *
     * @return int
     */
    public function getNumber(): int;

    /**
     * Part charset.
     *
     * @return null|string
     */
    public function getCharset(): ?string;

    /**
     * Part type.
     *
     * @return null|string
     */
    public function getType(): ?string;

    /**
     * Part subtype.
     *
     * @return null|string
     */
    public function getSubtype(): ?string;

    /**
     * Part encoding.
     *
     * @return null|string
     */
    public function getEncoding(): ?string;

    /**
     * Part disposition.
     *
     * @return null|string
     */
    public function getDisposition(): ?string;

    /**
     * Part description.
     *
     * @return null|string
     */
    public function getDescription(): ?string;

    /**
     * Part bytes.
     *
     * @return null|int|string
     */
    public function getBytes();

    /**
     * Part lines.
     *
     * @return null|string
     */
    public function getLines(): ?string;

    /**
     * Part parameters.
     *
     * @return Parameters
     */
    public function getParameters(): Parameters;

    /**
     * Get raw part content.
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * Get decoded part content.
     *
     * @return string
     */
    public function getDecodedContent(): string;

    /**
     * Part structure.
     *
     * @return \stdClass
     */
    public function getStructure(): \stdClass;

    /**
     * Get part number.
     *
     * @return string
     */
    public function getPartNumber(): string;

    /**
     * Get an array of all parts for this message.
     *
     * @return PartInterface[]
     */
    public function getParts(): array;
}
