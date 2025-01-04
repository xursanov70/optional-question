<?php

namespace App\Http\Controllers;

use App\Imports\QuestionsImport;
use App\Models\MakeTest;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\User;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;

class QuestionController extends Controller
{
    public function importData(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);
        try {
            Excel::import(new QuestionsImport, $request->file('file'));

            return response()->json(["message" => "Ma'lumotlar muvaffaqiyatli yuklandi!"], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => "Dasturda xatolik",
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ]);
        }
    }

    public function questions()
    {
        $startNumber = request('start_number');
        $endNumber = request('end_number');
        $key = request('test_category');
        $questions = Question::where('key', $key)
        ->where('test_number', '>=', $startNumber)
        ->where('test_number', '<=', $endNumber)
        ->get();

        $correctAnswers = $questions->pluck('correct_answer', 'id')->toArray();

        $questions = $questions->map(function ($question) {
            return [
                'id' => $question->id,
                'title' => $question->title,
                'a_variant' => $question->a_variant,
                'b_variant' => $question->b_variant,
                'c_variant' => $question->c_variant,
                'd_variant' => $question->d_variant,
                'active' => $question->active,
                'test_number' => $question->test_number
            ];
        });

        return view('questions', [
            'questions' => $questions,
            'correctAnswers' => $correctAnswers,
        ]);
    }

    public function exportExcelData()
    {
        $questions = Question::where('key', 'k_docx')
        ->get();
        $writer = WriterEntityFactory::createXLSXWriter();
        $filePath = 'storage/reports/' . date('Y_m_d_H_i_s') . 'report.xlsx';
        $writer->openToFile($filePath);
        $writer->addRow(WriterEntityFactory::createRowFromArray([
            'title',
            'a_variant',
            'b_variant',
            'c_variant',
            'd_variant',
            'correct_answer',
            'test_number',
            'key',
        ]));
        $data = [];

        foreach ($questions as $question) {
            $data[] = [
                'title' => $question->title,
                'a_variant' => $question->a_variant,
                'b_variant' => $question->b_variant,
                'c_variant' => $question->c_variant,
                'd_variant' => $question->d_variant,
                'correct_answer' => $question->correct_answer,
                'test_number' => $question->test_number,
                'key' => $question->key,
            ];
        }
        foreach ($data as $row) {
            $rowFromValues = WriterEntityFactory::createRowFromArray($row);
            $writer->addRow($rowFromValues);
        }
        $writer->close();
        return $filePath;
    }


    public function run()
    {
        $questions = [
            [
                'title' => 'Quyida keltirilgan kompyuter tarmoqlarining qaysi biri avval paydo bo’lgan?',
                'correct_answer_text' => 'Wide Area Network',
            ],
            [
                'title' => 'To’rtta bir-biri bilan bog’langan bog’lamlar strukturasi (kvadrat shaklida) qaysi topologiya turiga mansub?',
                'correct_answer_text' => 'Xalqa',
            ],
        ];

        foreach ($questions as $question) {
            $correct_answer_index = rand(0, 3); // Tog'ri javob uchun indeks tanlash

            // Fake variantlar yaratish
            $fake_answers = [
                Str::random(10),
                Str::random(10),
                Str::random(10),
                $question['correct_answer_text'],
            ];

            shuffle($fake_answers); // Variantlarni aralashtirish

            // Tog'ri javobni aniqlash
            $correct_answer = ['a', 'b', 'c', 'd'][$correct_answer_index];

            // Ma'lumotni jadvalga kiritish
            DB::table('test_questions')->insert([
                'title' => $question['title'],
                'a_variant' => $fake_answers[0],
                'b_variant' => $fake_answers[1],
                'c_variant' => $fake_answers[2],
                'd_variant' => $fake_answers[3],
                'correct_answer' => $correct_answer,
            ]);
        }
    }


    // public function importWordFile(Request $request)
    // {
    //     $file = $request->file('word_file');

    //     if ($file->getClientOriginalExtension() !== 'docx') {
    //         return response()->json(['success' => false, 'message' => 'Please upload a valid Word (.docx) file.']);
    //     }

    //     if ($file->isValid()) {
    //         $filePath = $file->getPathname();
    //         $phpWord = IOFactory::load($filePath);
    //         $text = '';
    //         $data = [];

    //         foreach ($phpWord->getSections() as $section) {
    //             foreach ($section->getElements() as $element) {
    //                 if ($element instanceof Text) {
    //                     $text .= $element->getText() . "\n";
    //                 } elseif ($element instanceof TextRun) {
    //                     foreach ($element->getElements() as $textElement) {
    //                         if ($textElement instanceof Text) {
    //                             $text .= $textElement->getText();
    //                         }
    //                     }
    //                     $text .= "\n";
    //                 }
    //             }
    //         }

    //         $lines = explode("\n", $text);
    //         $currentQuestion = '';
    //         $variants = [];
    //         $currentAnswer = '';
    //         // return count($lines);

    //         $data = [];





    //         foreach ($lines as $line) {
    //             // return $line;

    //             // if (substr($line, -2) == '?') {
    //             //     MakeTest::create([
    //             //         'title' => $line
    //             //     ]);
    //             //     $data['question'] = $line;
    //             // }
    //             if (preg_match('/^a\)/', $line)) {
    //                 $test = MakeTest::where('a_variant', null)->first();
    //                 $test->update([
    //                     'a_variant' => $line
    //                 ]);
    //                 $data['a_variant'] = $line;
    //             } elseif (preg_match('/^b\)/', $line)) {
    //                 $test = MakeTest::where('b_variant', null)->first();
    //                 $test->update([
    //                     'b_variant' => $line
    //                 ]);

    //                 $data['b_variant'] = $line;
    //             } elseif (preg_match('/^c\)/', $line)) {
    //                 MakeTest::whereNull('c_variant')->first()->update([
    //                     'c_variant' => $line
    //                 ]);
    //                 $data['c_variant'] = $line;
    //             } elseif (preg_match('/^d\)/', $line)) {
    //                 MakeTest::whereNull('d_variant')->first()->update([
    //                     'd_variant' => $line
    //                 ]);
    //                 $data['d_variant'] = $line;
    //             } elseif (strpos($line, "Javob: ") === 0) {
    //                 $answer = trim(substr($line, strlen("Javob: ")));
    //                 MakeTest::whereNull('correct_answer')->first()->update([
    //                     'correct_answer' => $answer
    //                 ]);
    //                 $data['correct_answer'] = $answer;
    //             }



    //             // if (preg_match('/^a\)/', $line)) {
    //             //     $data['a_variant'] = $line;
    //             // }
    //             // elseif(preg_match('/^b\)/', $line)) {
    //             //     $data['b_variant'] = $line;
    //             // }
    //             // }elseif(preg_match('/^c\)/', $line)) {
    //             //     $data[] = [
    //             //         'c_variant' => $line
    //             //     ];
    //             // }elseif(preg_match('/^d\)/', $line)) {
    //             //     $data[] = [
    //             //         'd_variant' => $line
    //             //     ];
    //             // }elseif (strpos($text, "Javob: ") === 0) { // Matnning boshlanishini tekshirish
    //             //     $answer = trim(substr($text, strlen("Javob: "))); // "Javob: " so'zidan keyingi qismini olish
    //             //     $data[] = [
    //             //         'correct_answer' => $answer
    //             //     ];
    //             // }





    //             // Str::after($line, 'a)');
    //             // $b_variant = Str::between($line, 'b)', 'c)');
    //             // $c_variant = Str::between($line, 'c)', 'd)');
    //             // $d_variant = Str::between($line, 'd)', 'Javob:');
    //             // $question = Str::before($line, '?');
    //             // $correct_answer = Str::after($line, 'Javob: ');

    //             // $a_variant = '';
    //             // $b_variant = '';
    //             // $c_variant = '';
    //             // $d_variant = '';
    //             // $correct_answer = '';

    //             // if (Str::after($line, 'a)')){
    //             //     $a_variant = $line;
    //             // }elseif(Str::after($line, 'b)')){
    //             //     $b_variant = $line;
    //             // }elseif(Str::after($line, 'c)')){
    //             //     $c_variant = $line;
    //             // }elseif(Str::after($line, 'd)')){
    //             //     $d_variant = $line;
    //             // }elseif(Str::after($line, 'Javob: ')){
    //             //     $correct_answer = Str::after($line, 'Javob: ');
    //             // }else{

    //             // }

    //             // $data [] = [
    //             //     'title' => "test",
    //             //     'a_variant' => $a_variant,
    //             //     'b_variant' => $b_variant,
    //             //     'c_variant' => $c_variant,
    //             //     'd_variant' => $d_variant,
    //             //     'correct_answer' => $correct_answer,
    //             // ];
    //             // $a_variant = "\n";
    //             // $b_variant = "\n";
    //             // $c_variant = "\n";
    //             // $d_variant = "\n";
    //             // $correct_answer = "\n";

    //             // $line = trim($line);
    //             // if (empty($line)) continue;

    //             // // Savol raqami va matnini ajratish
    //             // if (preg_match('/^(\d+)\.\s*(.+?)\s+a$/', $line, $matches)) {
    //             //     // Agar oldingi savol bo'lsa, uni saqlash
    //             //     if (!empty($currentQuestion)) {
    //             //         $data[] = [
    //             //             'title' => $currentQuestion,
    //             //             'a_variant' => $variants['a'] ?? '',
    //             //             'b_variant' => $variants['b'] ?? '',
    //             //             'c_variant' => $variants['c'] ?? '',
    //             //             'd_variant' => $variants['d'] ?? '',
    //             //             'correct_answer' => strtoupper($currentAnswer),
    //             //             'key' => 'computer_arch'
    //             //         ];
    //             //     }

    //             //     // Yangi savolni boshlash
    //             //     $currentQuestion = $matches[2];
    //             //     $variants = [];

    //             //     // Variantlarni ajratish
    //             //     preg_match('/a$\s*([^b]+)b$\s*([^c]+)c$\s*([^d]+)d$\s*([^\s]+)\s+Javob:\s*([a-d])/i', $line, $variantMatches);

    //             //     if (count($variantMatches) > 0) {
    //             //         $variants['a'] = trim($variantMatches[1]);
    //             //         $variants['b'] = trim($variantMatches[2]);
    //             //         $variants['c'] = trim($variantMatches[3]);
    //             //         $variants['d'] = trim($variantMatches[4]);
    //             //         $currentAnswer = trim($variantMatches[5]);
    //             //     }
    //             // }
    //         }
    //         // Oxirgi savolni qo'shish
    //         // if (!empty($currentQuestion)) {
    //         //     $data[] = [
    //         //         'title' => $currentQuestion,
    //         //         'a_variant' => $variants['a'] ?? '',
    //         //         'b_variant' => $variants['b'] ?? '',
    //         //         'c_variant' => $variants['c'] ?? '',
    //         //         'd_variant' => $variants['d'] ?? '',
    //         //         'correct_answer' => strtoupper($currentAnswer),
    //         //         'key' => 'computer_arch'
    //         //     ];
    //         // }

    //     }
    //     return $data;

    //     return response()->json(['success' => false, 'message' => 'File upload failed.']);
    // }

    public function importWordFile(Request $request)
    {
        $file = $request->file('word_file');

        if ($file->getClientOriginalExtension() !== 'docx') {
            return response()->json(['success' => false, 'message' => 'Please upload a valid Word (.docx) file.']);
        }

        if ($file->isValid()) {
            $filePath = $file->getPathname();
            $phpWord = IOFactory::load($filePath);
            $text = '';
            $data = [];

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof Text) {
                        $text .= $element->getText() . "\n";
                    } elseif ($element instanceof TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof Text) {
                                $text .= $textElement->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            $lines = explode("\n", trim($text)); // trim() qo'shildi
            $data = [];
            $isFirstQuestion = true; // Birinchi savol uchun flag
            $testCounter = 1; // Testlarni hisoblashni 1 dan boshlaymiz

            foreach ($lines as $line) {
                $line = trim($line); // Har bir qatorni trim qilish

                if (empty($line)) continue; // Bo'sh qatorlarni o'tkazib yuborish

                if (substr($line, -1) == '?' || (is_numeric(substr($line, 0, 2)) && strpos($line, '?'))) {
                    // Testni yaratish
                    Question::create([
                        'title' => $line,
                        'test_number' => $testCounter,
                        // 'key' => 'computer_arch'
                        // 'key' => 'k_docx'
                        'key' => 'signal'
                    ]);

                    $data['question'] = $line;
                    $testCounter++; // Keyingi test uchun test_number ni oshiramiz
                } elseif (preg_match('/^a\)/', $line)) {
                    $test = Question::whereNull('a_variant')->first();
                    if ($test) {
                        $test->update([
                            'a_variant' => $line
                        ]);
                    }
                    $data['a_variant'] = $line;
                } elseif (preg_match('/^b\)/', $line)) {
                    $test = Question::whereNull('b_variant')->first();
                    if ($test) {
                        $test->update([
                            'b_variant' => $line
                        ]);
                    }
                    $data['b_variant'] = $line;
                } elseif (preg_match('/^c\)/', $line)) {
                    $test = Question::whereNull('c_variant')->first();
                    if ($test) {
                        $test->update([
                            'c_variant' => $line
                        ]);
                    }
                    $data['c_variant'] = $line;
                } elseif (preg_match('/^d\)/', $line)) {
                    $test = Question::whereNull('d_variant')->first();
                    if ($test) {
                        $test->update([
                            'd_variant' => $line
                        ]);
                    }
                    $data['d_variant'] = $line;
                } elseif (strpos($line, "Javob: ") === 0) {
                    $answer = trim(substr($line, strlen("Javob: ")));
                    $test = Question::whereNull('correct_answer')->first();
                    if ($test) {
                        $test->update([
                            'correct_answer' => $answer
                        ]);
                    }
                    $data['correct_answer'] = $answer;
                }
            }

            return 'ok';
        }
    }




    // public function importWordFile(Request $request)
    // {
    //     $file = $request->file('word_file');

    //     if ($file->getClientOriginalExtension() !== 'docx') {
    //         return response()->json(['success' => false, 'message' => 'Please upload a valid Word (.docx) file.']);
    //     }

    //     if ($file->isValid()) {
    //         $filePath = $file->getPathname();
    //         $phpWord = IOFactory::load($filePath);
    //         $text = '';
    //         $data = [];

    //         foreach ($phpWord->getSections() as $section) {
    //             foreach ($section->getElements() as $element) {
    //                 if ($element instanceof Text) {
    //                     return  $text .= $element->getText() . "\n";
    //                 } elseif ($element instanceof TextRun) {
    //                     foreach ($element->getElements() as $textElement) {
    //                         if ($textElement instanceof Text) {
    //                             $text .= $textElement->getText();
    //                         }
    //                     }
    //                     $text .= "\n";
    //                 }
    //             }
    //         }

    //         $lines = explode("\n", $text);
    //         $currentQuestion = '';
    //         $currentAnswer = '';
    //         $expectedQuestionNumber = 1;

    //         foreach ($lines as $line) {
    //             $line = trim($line);
    //             if (empty($line)) continue;

    //             if (preg_match('/^(\d+)\./', $line, $matches)) {
    //                 $questionNumber = intval($matches[1]);

    //                 // Save the previous question if it exists
    //                 if (!empty($currentQuestion)) {
    //                     $data[] = [
    //                         'title' => $currentQuestion,
    //                         'correct_answer' => $currentAnswer,
    //                         // 'question_number' => $expectedQuestionNumber - 1
    //                         'key' => 'computer_arch'
    //                     ];
    //                     $currentQuestion = '';
    //                     $currentAnswer = '';
    //                 }

    //                 // Check if the question number is as expected
    //                 if ($questionNumber !== $expectedQuestionNumber) {
    //                     // Log a warning or handle the discrepancy as needed
    //                     Log::warning("Question number mismatch. Expected: $expectedQuestionNumber, Found: $questionNumber");
    //                 }

    //                 $currentQuestion = $line;
    //                 $expectedQuestionNumber = $questionNumber + 1;
    //             } elseif (str_starts_with(strtolower($line), 'javob:')) {
    //                 // This is an answer
    //                 $currentAnswer = trim(substr($line, 6)); // Remove "Javob:" from the beginning
    //             } elseif (!empty($currentQuestion)) {
    //                 // This is a continuation of the question
    //                 $currentQuestion .= ' ' . $line;
    //             }
    //         }

    //         // Add the last question if it exists
    //         if (!empty($currentQuestion)) {
    //             $data[] = [
    //                 'title' => $currentQuestion,
    //                 'correct_answer' => $currentAnswer,
    //                 // 'question_number' => $expectedQuestionNumber - 1,
    //                 'key' => 'computer_arch'
    //             ];
    //         }
    //         return $data;
    //         // foreach($data as $da){
    //         //     TestQuestion::create([
    //         //         'title' => $da['title'],
    //         //         'correct_answer' => $da['correct_answer'],
    //         //         'key' => 'computer_arch'
    //         //     ]);
    //         // }
    //         return 'ok';
    //         // // Insert into TestQuestion table
    //         // TestQuestion::insert($data);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Questions loaded from Word file!',
    //             'count' => count($data)
    //         ]);
    //     }

    //     return response()->json(['success' => false, 'message' => 'File upload failed.']);
    // }
}
