<?php

namespace App\Imports;

use App\Models\Question;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class QuestionsImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Question([
            'title' => $row['title'],
            'a_variant' => $row['a_variant'],
            'b_variant' => $row['b_variant'],
            'c_variant' => $row['c_variant'],
            'd_variant' => $row['d_variant'],
            'correct_answer' => $row['correct_answer'],
            // 'key' => 'IT',
            // 'number' => 1
        ]);
    }
}
