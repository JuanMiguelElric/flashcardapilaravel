<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = "categorias";

    protected $fillable = ["id","nome_categoria, user_id"];

    public function usuario():HasMany{
        return $this->hasMany(User::class);
    }
}
