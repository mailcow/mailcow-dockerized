<?php

namespace PhpMimeMailParser;

/**
 * Attachment of php-mime-mail-parser
 *
 * Fully Tested Mailparse Extension Wrapper for PHP 5.4+
 *
 */
class Attachment
{
    /**
     * @var string $filename Filename
     */
    protected $filename;

    /**
     * @var string $contentType Mime Type
     */
    protected $contentType;

    /**
     * @var string $content File Content
     */
    protected $content;

    /**
     * @var string $contentDisposition Content-Disposition (attachment or inline)
     */
    protected $contentDisposition;

    /**
     * @var string $contentId Content-ID
     */
    protected $contentId;

    /**
     * @var array $headers An Array of the attachment headers
     */
    protected $headers;

    /**
     * @var resource $stream
     */
    protected $stream;

    /**
     * @var string $mimePartStr
     */
    protected $mimePartStr;

    /**
     * Attachment constructor.
     *
     * @param string   $filename
     * @param string   $contentType
     * @param resource $stream
     * @param string   $contentDisposition
     * @param string   $contentId
     * @param array    $headers
     * @param string   $mimePartStr
     */
    public function __construct(
        $filename,
        $contentType,
        $stream,
        $contentDisposition = 'attachment',
        $contentId = '',
        $headers = [],
        $mimePartStr = ''
    ) {
        $this->filename = $filename;
        $this->contentType = $contentType;
        $this->stream = $stream;
        $this->content = null;
        $this->contentDisposition = $contentDisposition;
        $this->contentId = $contentId;
        $this->headers = $headers;
        $this->mimePartStr = $mimePartStr;
    }

    /**
     * retrieve the attachment filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Retrieve the Attachment Content-Type
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Retrieve the Attachment Content-Disposition
     *
     * @return string
     */
    public function getContentDisposition()
    {
        return $this->contentDisposition;
    }

    /**
     * Retrieve the Attachment Content-ID
     *
     * @return string
     */
    public function getContentID()
    {
        return $this->contentId;
    }

    /**
     * Retrieve the Attachment Headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get a handle to the stream
     *
     * @return stream
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Read the contents a few bytes at a time until completed
     * Once read to completion, it always returns false
     *
     * @param int $bytes (default: 2082)
     *
     * @return string|bool
     */
    public function read($bytes = 2082)
    {
        return feof($this->stream) ? false : fread($this->stream, $bytes);
    }

    /**
     * Retrieve the file content in one go
     * Once you retrieve the content you cannot use MimeMailParser_attachment::read()
     *
     * @return string
     */
    public function getContent()
    {
        if ($this->content === null) {
            fseek($this->stream, 0);
            while (($buf = $this->read()) !== false) {
                $this->content .= $buf;
            }
        }

        return $this->content;
    }

    /**
     * Get mime part string for this attachment
     *
     * @return string
     */
    public function getMimePartStr()
    {
        return $this->mimePartStr;
    }
}
