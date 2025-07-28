<?php

namespace App\Http\Controllers;


use App\Models\Question;
use App\Models\Test;
use App\Models\TestName;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;

class QuestionController extends Controller
{

    public function questions()
    {
        $startNumber = request('start_number');
        $endNumber = request('end_number');
        $key = request('test_category');
        $chatId = request('chat_id');

        $user = User::where('chat_id', $chatId)
            ->first();

        if (!$user) {
            abort(404, 'User not found');
        }
        if ($key != "xursanov70") {
            $testNameCount = TestName::where('chat_id', $chatId)->where('active', true)->count();
            if ($testNameCount > 1) {
                $oneMonthAgo = Carbon::now()->subMonth();
                if (Carbon::parse($user->payment_day)->lt($oneMonthAgo) || $user->payment_day == null) {
                    return view("payment-day");
                }
            }
        }
        $keyXursanov = $key == "xursanov70" ? true : false;

        $universalId = $keyXursanov ? env('XURSANOV_70_CHAT_ID') : $chatId;


        $questions = Question::where('key', $key)
            ->where('chat_id', $universalId)
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
            'chatId' => $chatId
        ]);
    }

    public function showTestForm()
    {
        $chatId = request("chat_id");
        $testNames = DB::table('test_names')->where('chat_id', $chatId)->where('active', true)->pluck('test_name');

        return view('home', compact('testNames'));
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

    public function uploadVideo(Request $request)
    {
        // 1. File borligini tekshiramiz
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'Fayl yuklanmadi!'], 400);
        }

        $file = $request->file('file');

        // 2. Fayl mp4 ekanligini tekshiramiz
        if ($file->getClientOriginalExtension() !== 'mp4') {
            return response()->json(['error' => 'Faqat mp4 fayllar qabul qilinadi!'], 400);
        }

        // 3. Saqlash
        $path = $file->storeAs('public/documents', 'manual.mp4', 'public');

        return response()->json(['success' => 'Video yuklandi', 'path' => $path]);
    }
}
