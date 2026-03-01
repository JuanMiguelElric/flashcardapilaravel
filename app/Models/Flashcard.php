<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    //
    protected $table = "flashcard";
    protected $fillable = ['categoria_id','user_id','count_flashcard_register'];
}
