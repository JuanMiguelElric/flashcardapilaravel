<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Estatísticas do dashboard, calculadas 100% a partir do MySQL
 * (flashcard_items/categorias) - nenhuma métrica de progresso/desempenho
 * (XP, streak, achievements) é exposta aqui porque não existe estrutura
 * persistida para isso (ver useUserStats.ts no frontend, que continua
 * 100% local/localStorage - decisão de produto pendente para persistir
 * gamificação no backend).
 */
class DashboardService
{
    public function statsForUser(User $user): array
    {
        $totalFlashcards = FlashcardItem::where('user_id', $user->id)->count();

        $flashcardsByCategoria = Categoria::where('categorias.user_id', $user->id)
            ->leftJoin('flashcard_items', 'flashcard_items.categoria_id', '=', 'categorias.id')
            ->groupBy('categorias.id', 'categorias.nome_categoria')
            ->orderBy('categorias.nome_categoria')
            ->selectRaw('categorias.id as categoria_id, categorias.nome_categoria, count(flashcard_items.id) as total')
            ->get();

        return [
            'total_flashcards' => $totalFlashcards,
            'total_categorias' => $flashcardsByCategoria->count(),
            'flashcards_by_categoria' => $flashcardsByCategoria->map(fn ($row) => [
                'categoria_id' => $row->categoria_id,
                'nome_categoria' => $row->nome_categoria,
                'total' => (int) $row->total,
            ])->values(),
            'flashcards_ultimos_7_dias' => FlashcardItem::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count(),
        ];
    }
}
