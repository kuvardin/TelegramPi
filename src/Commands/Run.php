<?php

declare(strict_types=1);

namespace Kuvardin\TelegramPi\Commands;

use Kuvardin\TelegramBotsApi\Bot;
use Kuvardin\TelegramBotsApi\Request;
use Kuvardin\TelegramPi\AbstractCommand;
use Kuvardin\TelegramPi\Input;

class Run extends AbstractCommand
{
    public static function getDescription(): string
    {
        return 'Run shell command';
    }

    public static function handle(Input $input, ?string $data): ?Request
    {
        if ($input->update->message === null) {
            return null;
        }

        $message = $input->update->message;
        if ($data === null) {
            $response_text = 'Enter shell command';
        } else {
            if (str_starts_with($data, 'timeout')) {
                $shell_command = "$data 2>&1";
            } else {
                $shell_command = "timeout 3m $data 2>&1";
            }

            ob_start();
            $result = shell_exec($shell_command);
            $error_text = ob_get_clean();

            if ($result === false) {
                $response_text = empty($error_text) ? 'false' : $error_text;
            } elseif ($result === null) {
                $response_text = empty($error_text) ? 'null' : $error_text;
            } elseif (trim($result) === '') {
                $response_text = empty($error_text) ? 'empty' : $error_text;
            } elseif (!empty($error_text)) {
                $response_text = "ERROR: $error_text\n$result";
            } else {
                $response_text = $result;
            }

            if (trim($response_text) === '') {
                $response_text = 'empty';
            }
        }

        if (mb_strlen($response_text) <= 1500) {
            return $input->bot->sendMessage(
                $message->chat->id,
                '<pre>' . Bot::filterString($response_text) . '</pre>',
                reply_to_message_id: $message->message_id,
            );
        }

        $response_text_full = $response_text;
        do {
            $result_part = mb_substr($response_text_full, 0, 1500);
            $response_text_full = mb_substr($response_text_full, 1500);
            $request = $input->bot->sendMessage(
                $message->chat->id,
                '<pre>' . Bot::filterString($result_part) . '</pre>',
                reply_to_message_id: $message->message_id,
            );

            if ($response_text_full === '') {
                return $request;
            }

            $request->sendRequest(3);
        } while ($response_text_full !== '');

        return null;
    }
}