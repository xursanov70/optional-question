<?php

namespace App\Http\Controllers\Telegram;

use App\Models\CheckUser;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Carbon\Carbon;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Stringable;

class TelegramHandler extends WebhookHandler
{

    public function start(): void
    {
        if ($this->message) {
            $firstName = $this->message->from()->firstName();
            $username = $this->message->from()->username();
            $lastName = $this->message->from()->lastName();
            $chatId = $this->message->from()->id();
            $this->createUser($chatId, $firstName, $username, $lastName);
        }
        $admin = $this->getAdmin($chatId);

        $this->chat->message('Assalamu alaykum ' . $firstName . ', Botimizga xush kelibsiz!')
            ->replyKeyboard(
                ReplyKeyboard::make()
                    ->button("👑 Standart test 👑")
                    ->button("👑 Avto guruh 👑")
                    ->button("👑 Mavzulashtirilgan testlar 👑")
                    ->button("💳 Obuna 💳")
                    ->button("📲 Telegram ma'lumotlarim 📲")
                    ->button("❇️ Natijalarim ❇️")
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("✅ Huquq berish ✅"))
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("❌ Huquq olish ❌"))
                    ->chunk(2)
                    ->inputPlaceholder("Assalamu alaykum...")
                    ->resize()
            )->send();
    }

    public function handleChatMessage(Stringable $text): void
    {
        if (!$this->message && !$this->callbackQuery) {
            $this->chat->message('Xatolik yuz berdi!')->send();
            return;
        }

        $firstName = $this->message->from()->firstName();
        $username = $this->message->from()->username();
        $lastName = $this->message->from()->lastName();
        $chatId = $this->message->from()->id();

        $this->createUser($chatId, $firstName, $username, $lastName);

        if ($text == "📲 Telegram ma'lumotlarim 📲") {
            $this->reply($this->getInfo($chatId, $username, $firstName));
            return;
        }
        $user = User::where('chat_id', $chatId)->where("active", true)->first();
        if (!$user) {
            $this->reply("Foydalanuvchi topilmadi!");
            return;
        }

        switch ($user->page) {
            case User::ADD_RULE_PAGE:
                $this->managaRule($chatId, $text, true);
                break;
            case User::REMOVE_RULE_PAGE:
                $this->managaRule($chatId, $text, false);
                break;
        }


        switch ($text) {
            case "✅ Huquq berish ✅":
                $this->rule($user, true);
                break;
            case "❌ Huquq olish ❌":
                $this->rule($user, false);
                break;
            case "👑 Standart test 👑":
                $this->standartTest($chatId);
                break;
            case "👑 Avto guruh 👑":
                $this->reply("Ushbu bo'limdan foydalanish sozlanmagan!");
                break;
            case "👑 Mavzulashtirilgan testlar 👑":
                $this->reply("Ushbu bo'limdan foydalanish sozlanmagan!");
                break;
            case "💳 Obuna 💳":
                $this->subscribe();
                break;
            case "❇️ Natijalarim ❇️":
                $this->result($user);
                break;
        }
    }

    private function result($user)
    {
        $message = "Natijalarim: \n\n";
        $message .= "Jami savollar: $user->total \n";
        $message .= "To'g'ri javoblar: $user->correct \n";
        $message .= "Noto'g'ri javoblar: $user->incorrect \n";
        $this->reply($message);
    }

    private function subscribe()
    {
        $reply = "Bot xizmatlaridan to'liq foydalanish (PREMIUM xizmatni ishga tushirish) uchun to'lov qilishingiz kerak \n\n" .
            "        - 1 oylik to'lov 92 ming so'm 
        - 2 haftalik to'lov 50 ming so'm 
        - 10 kunlik to'lov 40 ming so'm " .
            "\n\n 5614 6887 0305 9211 raqamli J. Xursanov nomidagi kartaga to'lov " .
            "qilib @jasko_70 profiliga to'lov chekni rasmini va Telegram ma'lumotlarim 📲 bo'limidan ID raqamingizni olib ikkalasini tashlab qo'yishingiz kerak bo'ladi " .
            "tez fursatda botdan to'liq foydalanishingiz mumkinligi to'g'risida sizga xabar boradi";
        return $this->reply($reply);
    }

    public function standartTest($chatId)
    {
        try {
            $user = User::where('chat_id', $chatId)->first();
            if ($user->subscribe_date < date("Y-m-d")) {
                Telegraph::chat($chatId)->message("Bo'limdan foydalanish uchun obuna sotib oling!")
                    ->send();
                return;
            }

            Telegraph::chat($chatId)->message('🖊 20 ta savol  ·  ⏱️ Umumiy test davomiyligi - 25 daqiqa')
                ->keyboard(Keyboard::make()->buttons([
                    Button::make('Bu testni boshlash')->action('beginTest')->param('chatId', $chatId),
                ]))->send();

            $user->update([
                "page" => User::STANDART_TEST
            ]);
        } catch (Exception $e) {
            Log::error("startdartTest xatosi: " . $e->getMessage());
            $this->reply("Xatolik yuz berdi, iltimos keyinroq urinib ko'ring.");
        }
    }


    public function beginTest($chatId)
    {
        try {
            $user = User::where('chat_id', $chatId)->first();
            $callbackQuery = request()->input('callback_query');
            $messageId = $callbackQuery['message']['message_id'] ?? null;

            if (!$user || $user->subscribe_date < date("Y-m-d")) {
                $this->reply("Bo'limdan foydalanish uchun obuna sotib oling!");
                return;
            }
            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();

            $user->update([
                'page' => User::TEST_IN_PROGRESS,
                'wrong_answers' => 0,
                'current_question_index' => 0
            ]);

            $question = Test::where('id', '>', $user->current_question_index)->first();

            if (!$question) {
                $this->reply("Hozirda savollar mavjud emas. Keyinroq urinib ko'ring!");
                return;
            }

            $this->sendQuestion($chatId, $question, $question->id);
        } catch (Exception $e) {
            Log::error("beginTest xatosi: " . $e->getMessage());
            $this->reply("Xatolik yuz berdi, iltimos keyinroq urinib ko'ring.");
        }
    }

    private function sendQuestion($chatId, $question, $questionNumber)
    {
        try {
            $message = "📝 $questionNumber - savol: " . $question->title . "\n\n";
            $rows = [];

            if ($question->a_variant) {
                $message .= "a) " . $question->a_variant . "\n";
                $rows[] = Button::make('a')->action('answerQuestion')->param('answer', 'a')->param('questionId', $question->id)->param('chatId', $chatId);
            }
            if ($question->b_variant) {
                $message .= "b) " . $question->b_variant . "\n";
                $rows[] = Button::make('b')->action('answerQuestion')->param('answer', 'b')->param('questionId', $question->id)->param('chatId', $chatId);
            }
            if ($question->c_variant) {
                $message .= "c) " . $question->c_variant . "\n";
                $rows[] = Button::make('c')->action('answerQuestion')->param('answer', 'c')->param('questionId', $question->id)->param('chatId', $chatId);
            }
            if ($question->d_variant) {
                $message .= "d) " . $question->d_variant . "\n";
                $rows[] = Button::make('d')->action('answerQuestion')->param('answer', 'd')->param('questionId', $question->id)->param('chatId', $chatId);
            }

            if (empty($rows)) {
                Telegraph::chat($chatId)->message("Bu savol uchun javob variantlari mavjud emas!")->send();
                return;
            }

            $keyboard = Keyboard::make()->row($rows);

            if ($question->question_image) {
                $filePath = 'media/' . $question->question_image;
                $absolutePath = storage_path('app/public/' . $filePath);

                if (!Storage::disk('public')->exists($filePath)) {
                    Telegraph::chat($chatId)->message('Rasm topilmadi!')->send();
                    return;
                }

                Telegraph::chat($chatId)->photo($absolutePath)
                    ->message($message)
                    ->keyboard($keyboard)
                    ->send();
            } else {
                Telegraph::chat($chatId)->message($message)
                    ->keyboard($keyboard)
                    ->send();
            }
        } catch (Exception $e) {
            Log::error("sendQuestion xatosi: " . $e->getMessage());
            Telegraph::message("Xatolik yuz berdi, iltimos keyinroq urinib ko'ring.")
                ->send();
        }
    }

    public function answerQuestion($questionId, $chatId, $answer)
    {
        try {
            $user = User::where('chat_id', $chatId)->first();
            $callbackQuery = request()->input('callback_query');
            $messageId = $callbackQuery['message']['message_id'] ?? null;

            if (!$user || $user->subscribe_date < date("Y-m-d")) {
                $this->reply("Bo'limdan foydalanish uchun obuna sotib oling!");
                return;
            }

            if (!$answer || !$questionId) {
                $this->reply("Javob yoki savol ID si topilmadi!");
                return;
            }

            $question = Test::find($questionId);
            if (!$question) {
                $this->reply("Savol topilmadi!");
                return;
            }
            $isCorrect = $question->correct_answer == $answer;
            $this->reply($isCorrect ? "✅ To'g'ri javob!" : "❌ Noto'g'ri javob!");

            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();

            if (!$isCorrect) {
                $user->update([
                    'wrong_answers' => DB::raw('wrong_answers + 1'),
                    'total' => DB::raw('total + 1'),
                    'incorrect' => DB::raw('incorrect + 1'),
                ]);
                $user->refresh();
            } else {
                $user->update([
                    'correct' => DB::raw('correct + 1'),
                    'total' => DB::raw('total + 1'),
                ]);
            }
            $message = "📝 $question->id - savol: " . $question->title . "\n\n\n";

            if ($question->a_variant) {
                $userMark = 'a' === $answer ? "❎ " : "";
                $inCorrectAnswer = $userMark == "" ? "❌ " : $userMark;
                $mark = $question->correct_answer == "a" ? "✅ " : $inCorrectAnswer;
                $message .=  $mark . "a) " . $question->a_variant . "\n\n";
            }
            if ($question->b_variant) {
                $userMark = 'b' ==  $answer ? "❎ " : "";
                $inCorrectAnswer = $userMark != "" ? $userMark : "❌ ";
                $mark = $question->correct_answer == "b" ? "✅ " : $inCorrectAnswer;
                $message .= $mark . "b) " . $question->b_variant . "\n\n";
            }
            if ($question->c_variant) {
                $userMark = 'c' ==  $answer ? "❎ " : "";
                $inCorrectAnswer = $userMark != "" ? $userMark : "❌ ";
                $mark = $question->correct_answer == "c" ? "✅ " : $inCorrectAnswer;
                $message .= $mark  . "c) " . $question->c_variant . "\n\n";
            }
            if ($question->d_variant) {
                $userMark = 'd' ==  $answer ? "❎ " : "";
                $inCorrectAnswer = $userMark != "" ? $userMark : "❌ ";
                $mark = $question->correct_answer == "d" ? "✅ " : $inCorrectAnswer;
                $message .= $mark . "d) " . $question->d_variant . "\n\n";
            }


            $filePath = 'media/' . $question->answer_media;
            $absolutePath = storage_path('app/public/' . $filePath);

            if (!Storage::disk('public')->exists($filePath)) {
                Telegraph::chat($chatId)->message('Savol uchun media topilmadi!')->send();
                return;
            }

            if ($question->media_status == Test::QUESTION_IMAGE) {
                $filePath = 'media/' . $question->question_image;
                $absolutePath = storage_path('app/public/' . $filePath);
                Telegraph::chat($chatId)->photo($absolutePath)
                    ->message($message)
                    ->send();
            } elseif ($question->media_status == Test::IMAGE) {
                Telegraph::chat($chatId)->photo($absolutePath)
                    ->message($message)
                    ->send();
            } elseif ($question->media_status == Test::GIF) {
                Telegraph::chat($chatId)->animation($absolutePath)
                    ->message($message)
                    ->send();
            } elseif ($question->media_status == Test::VIDEO) {
                Telegraph::chat($chatId)->video($absolutePath)
                    ->message($message)
                    ->send();
            }


            if ($user->wrong_answers >= 3) {
                $user->update([
                    'page' => User::HOME_PAGE,
                    'wrong_answers' => 0,
                    'current_question_index' => 0
                ]);
                Telegraph::chat($chatId)->message("❌ Sizda noto'g'ri javoblar soni 3 taga yetdi, Iltimos, qaytadan urinib ko'ring!")
                    ->send();
                return;
            }

            $currentIndex = $user->current_question_index + 1;

            if ($currentIndex == 20) {
                $user->update([
                    'page' => User::TEST_COMPLETED,
                    'current_question_index' => 0,
                    'wrong_answers' => 0
                ]);
                Telegraph::chat($chatId)->message("🎉 Tabriklaymiz! Testni muvaffaqqiyatli tugatdingiz!")
                    ->send();
                return;
            }

            $nextQuestion = Test::skip($currentIndex)->first();
            if (!$nextQuestion) {
                $user->update([
                    'page' => User::TEST_COMPLETED,
                    'current_question_index' => 0,
                    'wrong_answers' => 0
                ]);
                Telegraph::chat($chatId)->message("🎉 Tabriklaymiz! Testni muvaffaqqiyatli tugatdingiz!")
                    ->send();
                return;
            }

            $user->update([
                'current_question_index' => $currentIndex
            ]);

            $this->sendQuestion($chatId, $nextQuestion, $currentIndex + 1);
        } catch (Exception $e) {
            Log::error("answerQuestion xatosi: " . $e->getMessage());
            Telegraph::message("Xatolik yuz berdi, iltimos keyinroq urinib ko'ring.")
                ->send();
        }
    }



    private function managaRule($chatId, $text, $store)
    {
        $user = User::where('chat_id', $chatId)->first();
        $notFoundReply = $store ? "Botga start bosmagan foydalanuvchiga huquq bera olmaysiz!" : "Botga start bosmagan foydalanuvchidan huquq ola olmaysiz!";
        $reply = $store ? "Huquq muvafaqqiyatli berildi!" : "Huquq muvafaqqiyatli olindi!";
        $newUser = User::where('chat_id', $text)->first();
        if (!$newUser) {
            Telegraph::chat($chatId)->message($notFoundReply)->send();
            $user->update([
                "page" => User::HOME_PAGE
            ]);
            return;
        }
        if (!$store) {
            $user->update([
                "page" => User::HOME_PAGE
            ]);
            User::where('chat_id', $text)->update(['subscribe_date' => null, 'subscribe_type' => null]);
            Telegraph::chat($chatId)->message("Obuna muvaffaqqiyatli to'xtatildi")->send();
            return;
        }
        $keyboard = Keyboard::make()->row([
            Button::make('1 oylik')->action('giveSubscribe')->param('key', 1)->param('text', $text)->param('chatId', $chatId),
            Button::make('2 haftalik')->action('giveSubscribe')->param('key', 2)->param('text', $text)->param('chatId', $chatId),
            Button::make('10 kunlik')->action('giveSubscribe')->param('key', 3)->param('text', $text)->param('chatId', $chatId),
        ]);
        Telegraph::chat($chatId)->message("Iltimos obuna turini tanlang!\n")
            ->keyboard($keyboard)
            ->send();
    }

    public function giveSubscribe($chatId, $key, $text)
    {
        $callbackQuery = request()->input('callback_query');
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $user = User::where('chat_id', $text)->first();
        switch ($key) {
            case User::ONE_MONTH_SUBS:
                $user->update([
                    'subscribe_type' => User::ONE_MONTH_SUBS,
                    'subscribe_date' => Carbon::now()->addMonth(),
                ]);
                break;
            case User::TWO_WEEKS_SUBS:
                $user->update([
                    'subscribe_type' => User::TWO_WEEKS_SUBS,
                    'subscribe_date' => Carbon::now()->addWeeks(2),
                ]);
                break;
            case User::TEN_DAYS_SUBS:
                $user->update([
                    'subscribe_type' => User::TEN_DAYS_SUBS,
                    'subscribe_date' => Carbon::now()->addDays(10),
                ]);
                break;
        }
        Telegraph::chat($chatId)
            ->deleteMessage($messageId)
            ->send();
        User::where('chat_id', $chatId)->update(['page' => User::HOME_PAGE]);
        Telegraph::chat($chatId)->message("Obuna muvaffaqqiyatli berildi!")->send();
    }

    public function  test()
    {
        return Test::skip(1)->first();
    }

    private function getUserPage($chatId)
    {
        return User::where("chat_id", $chatId)
            ->where("active", true)
            ->first();
    }

    private function getAdmin($chatId)
    {
        return User::where("chat_id", $chatId)
            ->where("active", true)
            ->where("admin", true)
            ->first() ? true : false;
    }

    private function rule($user, $store)
    {
        if (!$user->admin) {
            Telegraph::chat($user->chat_id)->message("Sizda bunday huquq mavjud emas!")->send();
            return;
        }
        $user->update([
            "page" => $store ? User::ADD_RULE_PAGE : User::REMOVE_RULE_PAGE
        ]);
        Telegraph::chat($user->chat_id)->message("Iltimos, foydalanuvchi ID raqamini kiriting")->send();
    }


    public function share($chat)
    {
        $chat->message('Bot linkini ulashish👇')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('Ulashish')->switchInlineQuery("Savollar botiga obuna bo'lish uchun link ustiga bosing")
            ]))
            ->send();
    }

    public function admin()
    {
        return "Admin bilan bog'lanish 👉 @jasko_70";
    }

    public function getInfo($chatId, $username, $firstName)
    {
        $messageUsername = $username ?? "Mavjud emas!";
        $message = "Sizning ma'lumotlaringiz: \n\n";

        $message .= "Ism: " . $firstName . "\n";
        $message .= "Username: " . $messageUsername . "\n";
        $message .= "Telegram ID: " . $chatId . "\n";

        return $message;
    }

    private function createUser($chatId, $firstName, $username, $lastName)
    {
        $user = User::where('chat_id', $chatId)->where('active', true)->first();

        if (!$user) {
            User::create([
                'first_name' => $firstName,
                'username' => $username ?? "",
                'last_name' => $lastName ?? "",
                'chat_id' => $chatId
            ]);
        } else {
            $user->update([
                'first_name' => $firstName,
                'last_name' => $lastName ?? $user->last_name,
                'username' => $username ?? $user->username
            ]);
        }
    }
}
