<?php

namespace App\Http\Controllers\Telegram;

use App\Models\CheckUser;
use App\Models\Test;
use App\Models\User;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Facades\Telegraph;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Question;
use App\Models\TestName;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
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
            $user = $this->createUser($chatId, $firstName, $username, $lastName);
        }
        $url = env('APP_URL');
        $admin = $user->admin ? true : false;
        $storagePath = "public/documents/manual.mp4";
        $localPath = Storage::disk('public')->path($storagePath);



        $this->chat->message('Assalamu alaykum ' . $firstName . ', Botimizga xush kelibsiz!')
            ->replyKeyboard(
                ReplyKeyboard::make()
                    ->button("To'lov 💳")
                    ->button("Qo'llanma ⭐️")
                    ->button("Admin bilan aloqa 📞")
                    ->button("Test yaratish 📕")
                    ->button("Bosh sahifa 🏠")
                    ->button('Test yechish 📄')->webApp($url . "?chat_id=" . $chatId)
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq berish 🔐"))
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq olish 🔒"))
                    ->chunk(2)
                    ->inputPlaceholder("Assalamu alaykum...")
                    ->resize()
            )->send();

        if (Storage::disk('public')->exists($storagePath)) {
            $message = "Botdan foydalanish uchun qo'llanma: \n\nTest yechish bo'limida sinov uchun test yechib ko'ring va agar yoqsa o'zingiz uchun test yarating.\n\n1. O'zingiz uchun test yarating. \n2. Test yaratishda testlar yozilgan 'Test yaratish' bo'limida ko'rsatilgan sun'iy intelekt yordamida formatlab oling \n3. Formatlangan faylni bizga yuboring \n4. Biz siz yuborgan testlarni siz uchun 'Test yechish' bo'limida onlayn test ko'rinishida taqdim etamiz";
            Telegraph::chat($chatId)
                ->video($localPath)
                ->message($message)
                ->send();
            return;
        }
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

        $user = $this->createUser($chatId, $firstName, $username, $lastName);

        switch ($text) {
            case "Bosh sahifa 🏠":
                $this->updateUserPage($chatId, User::HOME_PAGE);
                Telegraph::chat($chatId)->message("Siz bosh sahifadasiz!")->send();
                return;
                break;
        }

        switch ($user->page) {
            case User::PREPARING_TEST:
                $this->makeTest($chatId, $this->message->document());
                break;
            case User::ENTER_TEST_NAME:
                if (!$this->check($text)) {
                    Telegraph::chat($chatId)->message("Iltimos, Test uchun nom kiriting!")->send();
                    return;
                }
                $this->verifyTestName($chatId, $text);
                break;
            case User::ADD_RULE:
                $this->manageRule($chatId, $text, true);
                break;
            case User::REMOVE_RULE:
                $this->manageRule($chatId, $text, false);
                break;
            case User::HOME_PAGE:
                switch ($text) {
                    case "To'lov 💳":
                        $this->sendInfo($chatId);
                        break;
                    case "Qo'llanma ⭐️":
                        $this->manual($chatId);
                        break;
                    case "Admin bilan aloqa 📞":
                        $this->contactAdmin($chatId);
                        break;
                    case "Test yaratish 📕":
                        $this->enterTestName($chatId);
                        break;
                    case "Huquq berish 🔐":
                        $this->addRule($chatId, true);
                        break;
                    case "Huquq olish 🔒":
                        $this->addRule($chatId, false);
                        break;
                }
                break;
        }
    }

    public function check($text)
    {
        if ($text == "To'lov 💳" || $text == "Qo'llanma ⭐️" || $text == "Admin bilan aloqa 📞" || $text == "Test yaratish 📕" || $text == "Huquq berish 🔐" || $text == "Huquq olish 🔒") {
            return false;
        }
        return true;
    }

    private function manual($chatId)
    {
        $storagePath = "public/documents/manual.mp4";
        $localPath = Storage::disk('public')->path($storagePath);

        $message = "Botdan foydalanish uchun qo'llanma: \n\n🪄 Test yaratish bo'limida bir martalik bepul test yarating\n🛎 Test yechish bo'limida testlarni bajarib ko'ring va agar ma'qul kelsa botga to'lov qilib botdan to'liq foydalanish huquqini oling.\n\n1. O'zingiz uchun test yarating. \n2. Test yaratishda testlar yozilgan 'Test yaratish' bo'limida ko'rsatilgan sun'iy intelekt yordamida formatlab oling \n3. Formatlangan faylni bizga yuboring \n4. Biz siz yuborgan testlarni siz uchun 'Test yechish' bo'limida onlayn test ko'rinishida taqdim etamiz";
        if (!Storage::disk('public')->exists($storagePath)) {
            Telegraph::chat($chatId)
                ->message($message)
                ->send();
            return;
        }

        Telegraph::chat($chatId)->message($message)->video($localPath)->send();
    }

    private function manageRule($chatId, $userChatId, $addRule)
    {
        $user = User::where('chat_id', $userChatId)->first();
        $admin = User::where('chat_id', $chatId)->where('admin', true)->first();
        if (!$admin) {
            Telegraph::chat($chatId)->message("Sizda bunday huquq yo'q!")->send();
            $this->updateUserPage($chatId, User::HOME_PAGE);
        }
        if (!$user) {
            Telegraph::chat($chatId)->message("Iltimos, botga start bosgan userning chat ID raqamini kiriting!")->send();
            return;
        }

        if ($addRule) {
            $user->update([
                "payment_day" => date("Y-m-d H:i:s")
            ]);
            Telegraph::chat($chatId)->message("Huquq muvaffaqqiyatli berildi")->send();
        } else {
            $user->update([
                "payment_day" => null
            ]);
            Telegraph::chat($chatId)->message("Huquq muvaffaqqiyatli olindi")->send();
        }
        $this->updateUserPage($chatId, User::HOME_PAGE);
    }

    private function addRule($chatId, $addRule)
    {
        Telegraph::chat($chatId)->message("Iltimos, foydalanuvchi chat_id raqamini kiriting:")->send();
        $page = $addRule ? User::ADD_RULE : User::REMOVE_RULE;
        $this->updateUserPage($chatId, $page);
    }


    private function verifyTestName($chatId, $testName)
    {
        if (strpos($testName, ' ') !== false) {
            Telegraph::chat($chatId)
                ->message("Iltimos, test nomida bo'shliq ishlatmang. Masalan: test_nomi yoki test1 kabi")
                ->send();
            return;
        }
        $oldTestName = TestName::where('chat_id', $chatId)->where('active', true)->where('test_name', $testName)->first();
        if ($oldTestName) {
            Telegraph::chat($chatId)->message("Sizda $testName nomli test mavjud. Iltimos boshqa nom kiriting:")->send();
            return;
        }
        $keyboard = Keyboard::make()->row([
            Button::make('Ha ✅')->action('verify')->param('verify', 'yes')->param('chatId', $chatId)->param("testName", $testName),
            Button::make("Yo'q ❌")->action('verify')->param('verify', 'no')->param('chatId', $chatId)->param("testName", $testName)
        ]);
        $message = "Test nomi 👉 $testName 👈 ekanligini tasdiqlaysizmi?";
        Telegraph::chat($chatId)->message($message)->keyboard($keyboard)->send();
    }

    public function verify($verify, $chatId, $testName)
    {
        $callbackQuery = request()->input('callback_query');
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        if ($verify == 'yes') {
            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();
            $this->createTest($chatId, $testName);
        } elseif ($verify == 'no') {
            Telegraph::chat($chatId)
                ->deleteMessage($messageId)
                ->send();
            $this->enterTestName($chatId);
        } else {
            Telegraph::chat($chatId)->message("Iltimos, Ha ✅ yoki Yo'q ❌ belsini tanlang!")->send();
        }
    }

    private function enterTestName($chatId)
    {
        $message = "Iltimos, Test uchun nom kiriting!";
        Telegraph::chat($chatId)->message($message)->send();
        $this->updateUserPage($chatId, User::ENTER_TEST_NAME);
    }

    private function sendInfo($chatId)
    {
        $username = env('USERNAME_TELEGRAM');
        $paymentSum = env('PAYMENT_SUM');
        $message = "To'lov summasi $paymentSum so'm. To'lov qilish uchun $username profiliga 👉 $chatId 👈 ushbu ID raqamingizni yuboring!";
        Telegraph::chat($chatId)->message($message)->send();
    }

    private function contactAdmin($chatId)
    {
        $username = env('USERNAME_TELEGRAM');
        $message = "Iltimos, admin bilan bog'lanish uchun $username profiliga murojaat qiling!";
        Telegraph::chat($chatId)->message($message)->send();
    }

    private function createTest($chatId, $testName)
    {

        TestName::create([
            "chat_id" => $chatId,
            "test_name" => $testName,
            "active" => false,
            "free" => false
        ]);
        $message = "Iltimos, quyidagi struktura bo'yicha testlar yozilgan faylni yuboring!";
        $message = "Iltimos, https://chatgpt.com ushbu sun'iy intelekt saytiga kirib, testlar yozilgan faylingizni va pastdagi tekstni yuboring\n Bu testlar yozilgan faylni formatlab beradi\nBizga formatlangan faylni yuboring";
        $structuraMessage = "You put a question mark '?' at the end of each question in this file,
you mark each question option as A), B), C) and D) and each question option should be written on a new line,
you mark each correct answer on a new line as Javob: A, Javob: B, Javob: C and Javob: D,
if there are no question options, create a fake one and send it to me as a docx file";
        // $structuraMessage = "1) Savollar fayli docx formatda bo'lsin \n 2) Har bir savolning oxirida ? so'roq belgisi bo'lsin \n 3) Har bir variantnig boshlanish qismi A) yoki a) variant harfi va qavs belgisi bo'lsin \n 4) Har vir savolning oxirida to'g'ri javob Javob: A yoki Javob: a ko'rinisha bo'lsin";
        $example = "Savollar ushbu ko'rinishda bo'lishi kerak: \n\n Apple so'zining ma'nosi nima? \n\n A) olma \n B) nok \n C) behi \n D) uzum \n\n Javob: A";
        $warning = "Eslatib o'tamiz, savollar quyidagi tartibda bo'lmasa, savol va to'g'ri javoblar aralashib ketishi mumkin!";
        Telegraph::chat($chatId)->message($message)->send();
        Telegraph::chat($chatId)->message($structuraMessage)->send();
        Telegraph::chat($chatId)->message($example)->send();
        Telegraph::chat($chatId)->message($warning)->send();
        $this->updateUserPage($chatId, User::PREPARING_TEST);
    }

    private function makeTest($chatId, $file)
    {
        try {
            if (!$file) {
                Telegraph::chat($chatId)
                    ->message('Iltimos, fayl yuboring!')
                    ->send();
                return;
            }
            $this->updateUserPage($chatId, User::MAKE_TEST);

            // Fayl ma'lumotlarini olish (Telegram Bot API orqali)
            $fileId = $file->id();
            $botToken = env('BOT_TOKEN');
            $response = Http::get("https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}");
            $fileInfo = $response->json();

            if (!$fileInfo['ok']) {
                throw new \Exception('Fayl ma\'lumotlarini olishda xato yuz berdi.');
            }

            $filePath = $fileInfo['result']['file_path'];
            $extension = pathinfo($filePath, PATHINFO_EXTENSION); // Fayl kengaytmasini aniqlash

            if (strtolower($extension) !== 'docx') {
                Telegraph::chat($chatId)
                    ->message('Iltimos, .docx formatdagi fayl yuboring!')
                    ->send();
                return;
            }

            $filename = 'document_' . time() . '_' . uniqid() . '.' . $extension;

            $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

            $storagePath = "public/documents/$filename";

            $fileContent = file_get_contents($fileUrl);
            Storage::disk('public')->put($storagePath, $fileContent);

            if (!Storage::disk('public')->exists($storagePath)) {
                Telegraph::chat($chatId)
                    ->message('Fayl topilmadi!')
                    ->send();
                return;
            }
            $testName = TestName::where("chat_id", $chatId)->where('active', false)->orderBy("id", "desc")->first();
            $name = $testName->test_name ?? "test";

            $localPath = Storage::disk('public')->path($storagePath);

            $this->importWordFile(new \SplFileObject($localPath), $chatId, $testName);
            $testName->update([
                "active" => true
            ]);



            Telegraph::chat($chatId)
                ->message('Fayl muvaffaqiyatli saqlandi!')
                // ->document('https://made-your-test-test.jprq.site/storage/public/documents/test.odt')
                ->send();

            Telegraph::chat($chatId)
                ->message("Test yechish bo'limida ko'rsangiz bo'ladi!")
                ->send();

            $this->updateUserPage($chatId, User::HOME_PAGE);
        } catch (\Exception $e) {
            Telegraph::chat($chatId)
                ->message('Faylni saqlashda xato yuz berdi: ' . $e->getMessage())
                ->send();
        }
    }

    private function updateUserPage($chatId, $page)
    {
        $user = User::where('chat_id', $chatId)->first();
        $user->update([
            "page" => $page
        ]);
    }


    public function share($chat)
    {
        $chat->message('Bot linkini ulashish👇')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('Ulashish')->switchInlineQuery("Savollar botiga obuna bo'lish uchun link ustiga bosing")
            ]))
            ->send();
    }

    public function createUser($chatId, $firstName, $username, $lastName)
    {
        $user = User::where('chat_id', $chatId)->where('active', true)->first();

        if (!$user) {
            $user = User::create([
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
        return $user;
    }


    public function importWordFile($file, $chatId, $testName)
    {
        Telegraph::chat($chatId)
            ->message('Yuklash jarayoni boshlandi...')
            ->send();

        $name = $testName->test_name ?? "test";

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
                Question::create([
                    'title' => $line,
                    'chat_id' => $chatId,
                    'test_number' => $testCounter,
                    'key' => $name,
                    'test_name_id' => $testName->id
                ]);

                $data['question'] = $line;
                $testCounter++;
            } elseif ((preg_match('/^a\)/', $line)) || (preg_match('/^A\)/', $line))) {
                $line = substr($line, 3);
                $test = Question::whereNull('a_variant')->first();
                if ($test) {
                    $test->update([
                        'a_variant' => $line
                    ]);
                }
                $data['a_variant'] = $line;
            } elseif ((preg_match('/^b\)/', $line)) || (preg_match('/^B\)/', $line))) {
                $line = substr($line, 3);
                $test = Question::whereNull('b_variant')->first();
                if ($test) {
                    $test->update([
                        'b_variant' => $line
                    ]);
                }
                $data['b_variant'] = $line;
            } elseif ((preg_match('/^c\)/', $line)) || (preg_match('/^C\)/', $line))) {
                $line = substr($line, 3);
                $test = Question::whereNull('c_variant')->first();
                if ($test) {
                    $test->update([
                        'c_variant' => $line
                    ]);
                }
                $data['c_variant'] = $line;
            } elseif ((preg_match('/^d\)/', $line)) || (preg_match('/^D\)/', $line))) {
                $line = substr($line, 3);
                $test = Question::whereNull('d_variant')->first();
                if ($test) {
                    $test->update([
                        'd_variant' => $line
                    ]);
                }
                $data['d_variant'] = $line;
            } elseif (strpos($line, "Javob: ") === 0) {
                $answer = strtolower(substr(trim($line), strlen("Javob: "), 1));
                $test = Question::whereNull('correct_answer')->first();
                if ($test) {
                    $test->update([
                        'correct_answer' => strtolower($answer)
                    ]);
                }
                $data['correct_answer'] = strtolower($answer);
            }
        }

        $testNameCount = TestName::where('chat_id', $chatId)->where('active', true)->count();
        if ($testNameCount < 1) {
            TestName::where('id', $testName->id)->update([
                'free' => true
            ]);
        }
    }
}
