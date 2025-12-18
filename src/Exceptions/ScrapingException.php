<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when web scraping fails.
 * Used to trigger API fallback mechanism.
 */
class ScrapingException extends \Exception
{
    /** HTTP 429 Too Many Requests */
    public const RATE_LIMITED = 429;

    /** Failed to parse HTML content */
    public const PARSE_ERROR = 1001;

    /** API request failed */
    public const API_ERROR = 1002;

    /** Network/connection error */
    public const NETWORK_ERROR = 1003;

    /**
     * Create a new ScrapingException
     *
     * @param string $message Error message
     * @param int $code Error code (use class constants)
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Check if this exception is due to rate limiting
     */
    public function isRateLimited(): bool
    {
        return $this->code === self::RATE_LIMITED;
    }

    /**
     * Create a rate limited exception
     */
    public static function rateLimited(string $message = "Rate limited by Liquipedia"): self
    {
        return new self($message, self::RATE_LIMITED);
    }

    /**
     * Create a parse error exception
     */
    public static function parseError(string $message = "Failed to parse response"): self
    {
        return new self($message, self::PARSE_ERROR);
    }

    /**
     * Create an API error exception
     */
    public static function apiError(string $message = "API request failed"): self
    {
        return new self($message, self::API_ERROR);
    }
}
