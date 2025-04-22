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
            $testCounter = 1; // Testlarni hisoblashni 1 dan boshlaymiz

            foreach ($lines as $line) {
                $line = trim($line); // Har bir qatorni trim qilish
                $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Apostrofni to‘g‘rilash

                if (empty($line)) continue; // Bo'sh qatorlarni o'tkazib yuborish

                if ((substr($line, -1) == '?' || substr($line, -1) == ':') || (is_numeric(substr($line, 0, 2)) && strpos($line, '?'))) {

                    $line = substr($line, strpos($line, '.') + 1);
                    Test::create([
                        'title' => $line,
                        'test_number' => $testCounter,
                        'key' => 'raqamli_iqtisod'
                    ]);

                    $data['question'] = $line;
                    $testCounter++; 
                } elseif (preg_match('/^a\)/', $line)) {
                    $line = substr($line, 3);
                    $test = Test::whereNull('a_variant')->first();
                    if ($test) {
                        $test->update([
                            'a_variant' => $line
                        ]);
                    }
                    $data['a_variant'] = $line;
                } elseif (preg_match('/^b\)/', $line)) {
                    $line = substr($line, 3);
                    $test = Test::whereNull('b_variant')->first();
                    if ($test) {
                        $test->update([
                            'b_variant' => $line
                        ]);
                    }
                    $data['b_variant'] = $line;
                } elseif (preg_match('/^c\)/', $line)) {
                    $line = substr($line, 3);
                    $test = Test::whereNull('c_variant')->first();
                    if ($test) {
                        $test->update([
                            'c_variant' => $line
                        ]);
                    }
                    $data['c_variant'] = $line;
                } elseif (preg_match('/^d\)/', $line)) {
                    $line = substr($line, 3);
                    $test = Test::whereNull('d_variant')->first();
                    if ($test) {
                        $test->update([
                            'd_variant' => $line
                        ]);
                    }
                    $data['d_variant'] = $line;
                } elseif (strpos($line, "Javob: ") === 0) {
                    $answer = trim(substr($line, strlen("Javob: ")));
                    $test = Test::whereNull('correct_answer')->first();
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


}
