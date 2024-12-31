<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    protected $fillable = [
        'title',
        'a_variant',
        'b_variant',
        'c_variant',
        'd_variant',
        'correct_answer',
        'key',
        'test_number',
        'active'
    ];
}
