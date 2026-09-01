<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStat extends Model
{
    //
    protected $table = "user_stat";
    protected $fillable = [
        "user_id",
        "totalXP",
        "level",
        "currentStreak",
        "longestStreak",
        "totalCardsStudied",
        "totalCorrect",
        "totalWrong",
        "categoryStats",
        "achievements",
    ];
}
