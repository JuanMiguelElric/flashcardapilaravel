<?php

namespace App\Services;

use App\Models\FlashcardItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Aplica o limite de flashcards do plano ativo do usuário, quando
 * definido.
 *
 * IMPORTANTE (bloqueio de produto - ver relatório da Parte 2): não existe,
 * em nenhum lugar do código ou documentação, um valor real de limite por
 * plano. `planos.limite_flashcards` é nullable e fica NULL até que o
 * produto defina os números reais por tier - com NULL (ou sem plano ativo
 * selecionado via plano_selecionado), esta checagem não bloqueia nada.
 * Não inventar um número aqui.
 */
class PlanLimitService
{
    public function assertFlashcardLimitNotExceeded(User $user): void
    {
        $limite = $user->planoAtivo()?->limite_flashcards;

        if ($limite === null) {
            return;
        }

        $atual = FlashcardItem::where('user_id', $user->id)->count();

        if ($atual >= $limite) {
            throw ValidationException::withMessages([
                'categoryId' => "Limite de {$limite} flashcards do plano atual foi atingido.",
            ]);
        }
    }
}
