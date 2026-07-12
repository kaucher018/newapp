<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['game_slug', 'game_name', 'algorithm_mode', 'normal_admin_percent', 'normal_user_percent'])]
class GameSetting extends Model
{
    // 
}