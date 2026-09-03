<?php

namespace App\Services;

use App\Exceptions\MercadoPagoException;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Único ponto de contato HTTP com o Mercado Pago (API de Assinaturas /
 * preapproval). Mesmo padrão de FlashcardServiceClient: config-driven,
 * retry só em falha transitória, exceção dedicada traduzida pelo
 * controller - o chamador nunca vê a exceção bruta do cliente HTTP.
 *
 * Autenticação: Bearer access_token da conta Mercado Pago (não é o token
 * Sanctum do usuário final).
 */
class MercadoPagoClient
{
    private string $baseUrl;

    private ?string $accessToken;

    private int $timeout;

    private int $retryTimes;

    private int $retryDelayMs;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mercadopago.base_url'), '/');
        $this->accessToken = config('services.mercadopago.access_token');
        $this->timeout = (int) config('services.mercadopago.timeout', 10);
        $this->retryTimes = max(1, (int) config('services.mercadopago.retry_times', 3));
        $this->retryDelayMs = (int) config('services.mercadopago.retry_delay_ms', 150);
    }

    /**
     * Cria uma assinatura recorrente (preapproval) para o usuário assinar
     * o plano. Retorna o payload bruto do Mercado Pago (inclui "id" e
     * "init_point", a URL de checkout hospedado para onde o usuário é
     * redirecionado).
     *
     * X-Idempotency-Key fixo por chamada lógica (não por tentativa de
     * retry) - se a resposta do MP se perder e o retry reenviar a mesma
     * chave, o MP não cria uma segunda assinatura duplicada.
     */
    public function criarAssinatura(User $user, Plano $plano, string $backUrl): array
    {
        $idempotencyKey = (string) Str::uuid();

        return $this->send(
            fn () => $this->request($idempotencyKey)->post('/preapproval', [
                'reason' => "FlashMind - Plano {$plano->name_plano}",
                'payer_email' => $user->email,
                'back_url' => $backUrl,
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => (float) $plano->valor,
                    'currency_id' => 'BRL',
                ],
                'status' => 'pending',
            ]),
            'criarAssinatura'
        );
    }

    /**
     * Busca o estado real de uma assinatura no Mercado Pago - usado ao
     * processar um webhook para confirmar o status atual em vez de
     * confiar cegamente no payload recebido (que pode estar desatualizado
     * ou ser forjado).
     */
    public function buscarAssinatura(string $mpSubscriptionId): array
    {
        return $this->send(
            fn () => $this->request()->get("/preapproval/{$mpSubscriptionId}"),
            'buscarAssinatura'
        );
    }

    private function request(?string $idempotencyKey = null)
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders(array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => $this->accessToken ? "Bearer {$this->accessToken}" : null,
                'X-Idempotency-Key' => $idempotencyKey,
            ]));
    }

    /**
     * Mesmo mecanismo de retry de FlashcardServiceClient::send() - só
     * falha transitória (timeout/conexão recusada/5xx), nunca 4xx.
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
                    Log::warning("MercadoPagoClient::{$operation} inalcançável, tentativa {$attempt}/{$this->retryTimes}", [
                        'message' => $e->getMessage(),
                    ]);

                    $this->wait($attempt);

                    continue;
                }

                Log::warning("MercadoPagoClient::{$operation} inalcançável após {$attempt} tentativas", [
                    'message' => $e->getMessage(),
                ]);

                throw MercadoPagoException::unreachable($operation, $e);
            }

            if ($response->serverError() && $attempt < $this->retryTimes) {
                Log::warning("MercadoPagoClient::{$operation} respondeu erro de servidor, tentativa {$attempt}/{$this->retryTimes}", [
                    'status' => $response->status(),
                ]);

                $this->wait($attempt);

                continue;
            }

            return $this->handle($response, $operation);
        }

        throw MercadoPagoException::unreachable($operation, $lastConnectionException ?? new \RuntimeException('Retry esgotado.'));
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
            Log::warning("MercadoPagoClient::{$operation} falhou", [
                'status' => $response->status(),
            ]);

            throw MercadoPagoException::unexpectedResponse($operation, $response->status());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
