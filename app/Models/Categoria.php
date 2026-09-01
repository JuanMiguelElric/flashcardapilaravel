<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = "categorias";

    protected $fillable = ["nome_categoria","icon","color", "user_id"];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flashcardItems(): HasMany
    {
        return $this->hasMany(FlashcardItem::class);
    }
}
