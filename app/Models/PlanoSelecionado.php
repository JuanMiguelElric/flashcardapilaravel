<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoSelecionado extends Model
{
        protected $table = "plano_selecionado";
        protected $fillable = ["id_usuario","id_plano","status"];
}
