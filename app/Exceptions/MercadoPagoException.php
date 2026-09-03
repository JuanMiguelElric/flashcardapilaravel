<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro de comunicação com o Mercado Pago. Nunca deve carregar a
 * mensagem/stack trace bruta da exceção original até o cliente - os
 * controllers traduzem isso para uma resposta JSON padronizada (502) sem
 * detalhes internos. Mesmo padrão de FlashcardServiceException.
 */
class MercadoPagoException extends Exception
{
    public static function unreachable(string $operation, ?\Throwable $previous = null): self
    {
        return new self("Falha ao comunicar com o Mercado Pago ({$operation}).", 0, $previous);
    }

    public static function unexpectedResponse(string $operation, int $status): self
    {
        return new self("Resposta inesperada do Mercado Pago ({$operation}): HTTP {$status}.", $status);
    }
}
