<?php

namespace App\Http\Controllers\Telegram;

use App\Models\CheckUser;
use App\Models\User;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
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


        if (!$this->checkUser($chatId)) {
            // Xabar yuboriladi + mavjud tugmalarni tozalash uchun bo'sh klaviatura
            $this->chat->message("Assalamu alaykum $firstName, Iltimos, Telegram ma'lumotlarim tugmasini bosib ID raqamingizni adminga yuboring \n va qayta kelib /start buyrug'ini bosing!")
                ->replyKeyboard(
                    ReplyKeyboard::make()
                        ->button("Telegram ma'lumotlarim 📲")
                        // ->button("Admin bilan aloqa 📞")
                        // ->button('Ulashish 📮')
                        // ->button('Test yechish 📄')
                        ->chunk(2)
                        ->inputPlaceholder("Assalamu alaykum...")
                        ->resize()
                )->send();
            return;
        }
        $url = env('APP_URL');

        // Agar foydalanuvchiga ruxsat bo‘lsa - tugmalar ko‘rsatiladi
        $this->chat->message('Assalamu alaykum ' . $firstName . ', Botimizga xush kelibsiz!')
            ->replyKeyboard(
                ReplyKeyboard::make()
                    ->button("Telegram ma'lumotlarim 📲")
                    ->button("Admin bilan aloqa 📞")
                    ->button('Ulashish 📮')
                    ->button('Test yechish 📄')->webApp($url . "?chat_id=" . $chatId)
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq berish"))
                    ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq olish"))
                    ->chunk(2)
                    ->inputPlaceholder("Assalamu alaykum...")
                    ->resize()
            )->send();
    }

    private function checkUser($chatId): bool
    {
        return CheckUser::where("chat_id", $chatId)->where("active", true)->first() ? true : false;
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

        // Foydalanuvchi ma'lumotlarini yaratish
        $this->createUser($chatId, $firstName, $username, $lastName);

        if ($text == "Telegram ma'lumotlarim 📲") {
            $this->reply($this->getInfo($chatId, $username, $firstName));
            return;
        }
        $admin = $this->getAdmin($chatId);

        $userPage = User::where("chat_id", $chatId)
            ->where("active", true)
            ->first();
        if ($userPage->page == User::ADD_RULE_PAGE) {
            $user = User::where('chat_id', $text)->first();
            if (!$user) {
                $this->reply("Botga start bosmagan userga huquq bera olmaysiz!");
                $userPage->update([
                    "page" => User::HOME_PAGE
                ]);
                return;
            }
            $checkUser =  CheckUser::where("chat_id", $text)->first();
            if (!$checkUser) {
                CheckUser::create([
                    "chat_id" => $text
                ]);
            } else {
                $checkUser->update([
                    "active" => true
                ]);
            }
            $userPage->update([
                "page" => User::HOME_PAGE
            ]);
            $this->reply("Huquq muvafaqqiyatli berildi!");
        } elseif ($userPage->page == User::REMOVE_RULE_PAGE) {
            $user = User::where('chat_id', $text)->first();
            if (!$user) {
                $this->reply("Botga start bosmagan userdan huquq ololmaysiz!");
                $userPage->update([
                    "page" => User::HOME_PAGE
                ]);
                return;
            }
            $checkUser =  CheckUser::where("chat_id", $text)->first();
            if (!$checkUser) {
                CheckUser::create([
                    "chat_id" => $text,
                    "active" => false
                ]);
            } else {
                $checkUser->update([
                    "active" => false
                ]);
            }
            $userPage->update([
                "page" => User::HOME_PAGE
            ]);
            $this->reply("Huquq muvafaqqiyatli olindi!");
        }

        if (!$this->checkUser($chatId)) {
            $this->chat->message("Assalamu alaykum $firstName, Iltimos, Telegram ma'lumotlarim tugmasini bosib, ID raqamingizni adminga yuboring \nva qayta kelib /start buyrug'ini bosing!")
                ->replyKeyboard(
                    ReplyKeyboard::make()
                        ->button("Telegram ma'lumotlarim 📲")
                        // ->button("Admin bilan aloqa 📞")
                        // ->button('Ulashish 📮')
                        // ->button('Test yechish 📄')
                        ->chunk(2)
                        ->inputPlaceholder("Assalamu alaykum...")
                        ->resize()
                )->send();
            return;
        }

        // Foydalanuvchi ruxsatli bo'lsa, xabar matniga qarab harakat
        switch ($text) {
            case "Huquq berish":
                $this->addedRule($chatId);
                break;
            case "Huquq olish":
                $this->removeRule($chatId);
                break;
            case "Admin bilan aloqa 📞":
                $this->reply($this->admin());
                break;
            case "Ulashish 📮":
                $this->share($this->chat);
                break;
            case "Test yechish 📄":
                if (!$this->checkUser($chatId)) {
                    $this->chat->message("Kechirasiz, test yechish uchun foydalanish taqiqlangan!")->send();
                    return;
                }
                $url = env('APP_URL');
                $this->chat->message('Iltimos, qaytadan Test yechish tugmasini bosing!')
                    ->replyKeyboard(
                        ReplyKeyboard::make()
                            ->button("Telegram ma'lumotlarim 📲")
                            ->button("Admin bilan aloqa 📞")
                            ->button('Ulashish 📮')
                            ->button('Test yechish 📄')->webApp($url . "?chat_id=" . $chatId)
                            ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq berish"))
                            ->when($admin, fn(ReplyKeyboard $keyboard) => $keyboard->button("Huquq olish"))
                            ->chunk(2)
                            ->inputPlaceholder("Assalamu alaykum...")
                            ->resize()
                    )->send();
                break;
        }
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

    private function addedRule($chatId)
    {
        $user =  User::where('chat_id', $chatId)
            ->where('admin', true)
            ->first();
        if (!$user) {
            $this->reply("Sizda bunday huquq mavjud emas!");
            return;
        }
        $user->update([
            "page" => User::ADD_RULE_PAGE
        ]);
        $this->reply("Iltimos, foydalanuvchi ID raqamini kiriting");
    }

    private function removeRule($chatId)
    {
        $user =  User::where('chat_id', $chatId)
            ->where('admin', true)
            ->first();
        if (!$user) {
            $this->reply("Sizda bunday huquq mavjud emas!");
            return;
        }
        $user->update([
            "page" => User::REMOVE_RULE_PAGE
        ]);
        $this->reply("Iltimos, foydalanuvchi ID raqamini kiriting");
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

    public function createUser($chatId, $firstName, $username, $lastName)
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
