<?php
declare(strict_types=1);

namespace NeuroFinder\Http;

final class Response
{
    private ?string $rawBody = null;

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

    public static function html(int $status, string $html, array $headers = []): self
    {
        $response = new self($status, [], array_merge(['Content-Type: text/html; charset=utf-8'], $headers));
        $response->rawBody = $html;
        return $response;
    }

    public static function raw(int $status, string $content, array $headers = []): self
    {
        $response = new self($status, [], $headers);
        $response->rawBody = $content;
        return $response;
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

        if ($this->rawBody !== null) {
            echo $this->rawBody;
            return;
        }

        echo json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}


