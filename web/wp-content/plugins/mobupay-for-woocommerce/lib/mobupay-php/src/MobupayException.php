<?php

declare(strict_types=1);

namespace Mobupay;

/**
 * Exception levee par le SDK Mobupay (erreur reseau, erreur API, signature webhook
 * invalide). Porte le code HTTP et le corps de reponse decode si disponibles.
 */
final class MobupayException extends \RuntimeException
{
    /** @var array<string,mixed>|null */
    private ?array $responseBody;

    /** @param array<string,mixed>|null $responseBody */
    public function __construct(string $message, int $httpStatus = 0, ?array $responseBody = null)
    {
        parent::__construct($message, $httpStatus);
        $this->responseBody = $responseBody;
    }

    /** @return array<string,mixed>|null */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
