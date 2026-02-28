<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plano extends Model
{
    protected $table = "tables";
    protected $fillable = ["nome_plano","descricao","valor","desconto"];
}
