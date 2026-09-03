<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Aplica as regras comerciais do plano ativo do usuário: limite de
 * flashcards por período de 30 dias, limite de categorias, e quais tipos
 * de flashcard são permitidos. Valores reais vêm de `planos` (seedados em
 * seed_planos_oficiais) - nada aqui é hardcoded.
 */
class PlanLimitService
{
    private ?Plano $gratuitoCache = null;

    /**
     * Plano ativo do usuário, ou o Gratuito como fallback - garante que
     * "sem plano selecionado" nunca seja tratado como "sem limite"
     * (fail-closed). Depois de seed_planos_oficiais +
     * backfill_plano_gratuito_para_usuarios_sem_plano, todo usuário real
     * já tem um plano ativo; este fallback é defesa em profundidade, não
     * o caminho esperado.
     */
    public function resolveActivePlano(User $user): Plano
    {
        return $user->planoAtivo() ?? $this->gratuitoFallback();
    }

    public function assertFlashcardLimitNotExceeded(User $user): void
    {
        $plano = $this->resolveActivePlano($user);

        if ($plano->limite_flashcards === null) {
            return;
        }

        $atual = FlashcardItem::where('user_id', $user->id)
            ->where('created_at', '>=', $this->periodoAtualInicio($user))
            ->count();

        if ($atual >= $plano->limite_flashcards) {
            throw ValidationException::withMessages([
                'categoryId' => "Limite de {$plano->limite_flashcards} flashcards deste período (30 dias) foi atingido.",
            ]);
        }
    }

    public function assertCategoriaLimitNotExceeded(User $user): void
    {
        $plano = $this->resolveActivePlano($user);

        if ($plano->limite_categorias === null) {
            return;
        }

        $atual = Categoria::where('user_id', $user->id)->count();

        if ($atual >= $plano->limite_categorias) {
            throw ValidationException::withMessages([
                'nome_categoria' => "Limite de {$plano->limite_categorias} categorias do plano atual foi atingido.",
            ]);
        }
    }

    public function assertFlashcardTypeAllowed(User $user, string $type): void
    {
        $plano = $this->resolveActivePlano($user);

        $bloqueado = match ($type) {
            'audio' => ! $plano->permite_audio,
            'multiple-choice' => ! $plano->permite_multipla_escolha,
            default => false, // summary/open-ended = texto, sempre permitido
        };

        if ($bloqueado) {
            throw ValidationException::withMessages([
                'type' => "O tipo de flashcard '{$type}' não está disponível no plano {$plano->name_plano}.",
            ]);
        }
    }

    /**
     * Início do período de 30 dias corridos vigente, ancorado em
     * user.created_at (decisão de produto: janela rolante desde o
     * cadastro, não mês calendário). Ex.: cadastro em D, hoje é D+65 ->
     * bloco atual é [D+60, D+90).
     */
    private function periodoAtualInicio(User $user): Carbon
    {
        $ancora = $user->created_at;
        $blocosCompletos = intdiv((int) $ancora->diffInDays(now()), 30);

        return $ancora->copy()->addDays($blocosCompletos * 30);
    }

    private function gratuitoFallback(): Plano
    {
        return $this->gratuitoCache ??= Plano::where('name_plano', 'Gratuito')->firstOrFail();
    }
}
