<?php

namespace App\Http\Controllers\Telegram;

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

        $this->chat->message('Assalamu alaykum '. $firstName . ', Botimizga xush kelibsiz!')
            ->replyKeyboard(
                ReplyKeyboard::make()
                    ->button("Telegram ma'lumotlarim 📲")
                    ->button("Admin bilan aloqa 📞")
                    ->button('Ulashish 📮')
                    ->button('Test yechish 📄')->webApp(env('APP_URL'))
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

        switch ($text) {
            case "Telegram ma'lumotlarim 📲":
                $this->reply($this->getInfo($chatId, $username, $firstName));
                break;
            case "Admin bilan aloqa 📞":
                $this->reply($this->admin());
                break;
            case "Ulashish 📮":
                $this->share($this->chat);
                break;
        }
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
