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
        'answer_media',
        'test_number',
        'active'
    ];

    const QUESTION_IMAGE = 1;
    const IMAGE = 2;
    const GIF = 3;
    const VIDEO = 4;
}
