<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckUser extends Model
{
    protected $fillable = [
        "chat_id",
        "active"
    ];
}
