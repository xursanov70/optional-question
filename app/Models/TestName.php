<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestName extends Model
{
    protected $fillable = [
        "test_name",
        "chat_id",
        "active",
        "free"
    ];
}
