<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoSelecionado extends Model
{
        protected $table = "plano_selecionado";
        protected $fillable = ["id_usuario","id_plano","status","expira_em","mp_subscription_id"];

        protected function casts(): array
        {
                return ['expira_em' => 'datetime'];
        }

        public function plano(): BelongsTo
        {
                return $this->belongsTo(Plano::class, 'id_plano');
        }
}
