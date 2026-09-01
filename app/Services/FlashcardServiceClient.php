<?php

namespace App\Services;

use App\Exceptions\FlashcardServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Único ponto de contato HTTP com o microsserviço Python + Neo4j.
 *
 * Ninguém além do App\Services\FlashcardService deve chamar isto
 * diretamente. O React nunca fala com o Python - só o Laravel.
 *
 * Autenticação serviço-a-serviço: header X-Service-Token, validado pelo
 * Python (não é o token Sanctum do usuário final).
 */
class FlashcardServiceClient
{
    private string $baseUrl;
    private ?string $token;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.flashcard_service.url'), '/');
        $this->token = config('services.flashcard_service.token');
        $this->timeout = (int) config('services.flashcard_service.timeout', 10);
    }

    /**
     * Envia o conteúdo de um flashcard recém-criado no MySQL para o Python.
     * $flashcardId é o ID canônico (flashcard_items.id) - o Python deve
     * persistir esse valor como flashcard_id no node do Neo4j.
     */
    public function submitFlashcard(int $flashcardId, array $payload): array
    {
        return $this->send(
            fn () => $this->request()->post('/submit_flash', array_merge($payload, [
                'flashcard_id' => $flashcardId,
            ])),
            'submitFlashcard'
        );
    }

    /**
     * Busca o conteúdo dos flashcards de um usuário.
     *
     * $userId vem sempre de Auth::id() no Laravel, nunca de input do
     * cliente. Enviamos como filtro para o Python (quando ele passar a
     * suportar), mas o chamador (FlashcardService) NÃO deve confiar
     * cegamente nesse filtro - deve validar novamente contra os
     * flashcard_items pertencentes a este usuário no MySQL.
     */
    public function fetchIndexForUser(int $userId): array
    {
        return $this->send(
            fn () => $this->request()->get('/flashcard/index', ['user_id' => $userId]),
            'fetchIndexForUser'
        );
    }

    /**
     * Atualiza o conteúdo de um flashcard existente.
     */
    public function updateFlashcard(int $flashcardId, array $payload): array
    {
        return $this->send(
            fn () => $this->request()->put("/flashcard/{$flashcardId}", $payload),
            'updateFlashcard'
        );
    }

    /**
     * Remove o conteúdo de um flashcard.
     *
     * O Python valida ownership a partir do campo "usuario" no corpo do
     * DELETE - sem ele, a exclusão é rejeitada (ou, pior, aceita sem
     * checagem). $userId vem sempre de Auth::id(), nunca de input do
     * cliente.
     */
    public function deleteFlashcard(int $flashcardId, int $userId): void
    {
        $this->send(
            fn () => $this->request()->delete("/flashcard/{$flashcardId}", [
                'usuario' => $userId,
            ]),
            'deleteFlashcard'
        );
    }

    private function request()
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders(array_filter([
                'Content-Type' => 'application/json',
                'X-Service-Token' => $this->token,
            ]));
    }

    /**
     * Executa a chamada HTTP, traduzindo qualquer falha (timeout, conexão
     * recusada, status >= 400) em FlashcardServiceException - o chamador
     * nunca vê a exceção bruta do cliente HTTP.
     */
    private function send(\Closure $call, string $operation): array
    {
        try {
            $response = $call();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("FlashcardServiceClient::{$operation} inalcançável", [
                'message' => $e->getMessage(),
            ]);

            throw FlashcardServiceException::unreachable($operation, $e);
        }

        return $this->handle($response, $operation);
    }

    private function handle($response, string $operation): array
    {
        if ($response->failed()) {
            Log::warning("FlashcardServiceClient::{$operation} falhou", [
                'status' => $response->status(),
            ]);

            throw FlashcardServiceException::unexpectedResponse($operation, $response->status());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
