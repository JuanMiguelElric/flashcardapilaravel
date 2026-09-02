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

    private int $retryTimes;

    private int $retryDelayMs;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.flashcard_service.url'), '/');
        $this->token = config('services.flashcard_service.token');
        $this->timeout = (int) config('services.flashcard_service.timeout', 10);
        $this->retryTimes = max(1, (int) config('services.flashcard_service.retry_times', 3));
        $this->retryDelayMs = (int) config('services.flashcard_service.retry_delay_ms', 150);
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
     * cliente. Enviamos como filtro para o Python, mas o chamador
     * (FlashcardService) NÃO deve confiar cegamente nesse filtro - deve
     * validar novamente contra os flashcard_items pertencentes a este
     * usuário no MySQL.
     *
     * $page/$perPage espelham a página MySQL sendo servida (mesma
     * ordenação por id) - o Python já suporta esses parâmetros
     * (IndexQueryParams), mas antes desta mudança o Laravel nunca os
     * enviava, então o conteúdo vinha sempre limitado ao DEFAULT_PAGE_SIZE
     * (50) do Python, independente da página real do MySQL. FlashcardService
     * ainda limita o resultado final aos ids da página MySQL - Python aqui
     * é só fonte de conteúdo, não de paginação.
     */
    public function fetchIndexForUser(int $userId, int $page = 1, int $perPage = 50): array
    {
        return $this->send(
            fn () => $this->request()->get('/flashcard/index', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
            ]),
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
     * Executa a chamada HTTP, com retry para falhas transitórias
     * (timeout/conexão recusada ou 5xx do Python), traduzindo a falha
     * definitiva (esgotadas as tentativas, ou status 4xx) em
     * FlashcardServiceException - o chamador nunca vê a exceção bruta do
     * cliente HTTP.
     *
     * Retry é seguro aqui porque toda escrita no Python é idempotente
     * (MERGE por flashcard_id, nunca por título): reenviar a mesma
     * requisição depois de uma resposta perdida não cria duplicação, e é
     * exatamente o cenário (resposta HTTP perdida após o Python já ter
     * persistido no Neo4j) em que o retry resolve a inconsistência ao
     * invés de deixar o MySQL ser revertido enquanto o Neo4j já commitou.
     */
    private function send(\Closure $call, string $operation): array
    {
        $lastConnectionException = null;

        for ($attempt = 1; $attempt <= $this->retryTimes; $attempt++) {
            try {
                $response = $call();
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastConnectionException = $e;

                if ($attempt < $this->retryTimes) {
                    Log::warning("FlashcardServiceClient::{$operation} inalcançável, tentativa {$attempt}/{$this->retryTimes}", [
                        'message' => $e->getMessage(),
                    ]);

                    $this->wait($attempt);

                    continue;
                }

                Log::warning("FlashcardServiceClient::{$operation} inalcançável após {$attempt} tentativas", [
                    'message' => $e->getMessage(),
                ]);

                throw FlashcardServiceException::unreachable($operation, $e);
            }

            if ($response->serverError() && $attempt < $this->retryTimes) {
                Log::warning("FlashcardServiceClient::{$operation} respondeu erro de servidor, tentativa {$attempt}/{$this->retryTimes}", [
                    'status' => $response->status(),
                ]);

                $this->wait($attempt);

                continue;
            }

            return $this->handle($response, $operation);
        }

        // Não deveria ser alcançável (o laço sempre retorna ou lança
        // dentro de si), mas mantém o contrato de tipo do método.
        throw FlashcardServiceException::unreachable($operation, $lastConnectionException ?? new \RuntimeException('Retry esgotado.'));
    }

    private function wait(int $attempt): void
    {
        if ($this->retryDelayMs <= 0) {
            return;
        }

        usleep($this->retryDelayMs * 1000 * $attempt);
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
