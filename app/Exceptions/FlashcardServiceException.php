<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro de comunicação com o microsserviço de conteúdo (Python + Neo4j).
 * Nunca deve carregar a mensagem/stack trace bruta da exceção original
 * até o cliente - os controllers traduzem isso para uma resposta JSON
 * padronizada (502/504) sem detalhes internos.
 */
class FlashcardServiceException extends Exception
{
    public static function unreachable(string $operation, ?\Throwable $previous = null): self
    {
        return new self("Falha ao comunicar com o serviço de flashcards ({$operation}).", 0, $previous);
    }

    public static function unexpectedResponse(string $operation, int $status): self
    {
        return new self("Resposta inesperada do serviço de flashcards ({$operation}): HTTP {$status}.", $status);
    }
}
