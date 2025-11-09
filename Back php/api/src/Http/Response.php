<?php
declare(strict_types=1);

namespace NeuroFinder\Http;

final class Response
{
    public function __construct(
        private readonly int $status,
        private readonly array $body = [],
        private readonly array $headers = []
    ) {
    }

    public static function ok(array $body): self
    {
        return new self(200, $body);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, ['error' => $message]);
    }

    public static function notFound(string $message): self
    {
        return new self(404, ['error' => $message]);
    }

    public static function serverError(string $message): self
    {
        return new self(500, ['error' => $message]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $header) {
            header($header);
        }

        if ($this->status === 204) {
            return;
        }

        echo json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}


