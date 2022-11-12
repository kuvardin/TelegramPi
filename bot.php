<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Kuvardin\TelegramBotsApi\Bot;
use Kuvardin\TelegramBotsApi\Exceptions\TelegramBotsApiException;
use Kuvardin\TelegramPi\Controller;
use Kuvardin\TelegramPi\Input;

const ROOT_DIR = __DIR__;
$loader = require ROOT_DIR . '/vendor/autoload.php';
$loader->addPsr4('Kuvardin\\TelegramPi\\', ROOT_DIR . '/src/');

$settings = require 'settings.php';

$client = new Client([
    RequestOptions::VERIFY => false,
]);

$bot = new Bot($client, $settings['token']);

while (true) {
    try {
        $bot->getMe()->sendRequest();
        break;
    } catch (GuzzleException $exception) {
        printf(
            "%s #%d: %s\n",
            get_class($exception),
            $exception->getCode(),
            $exception->getMessage(),
        );
        sleep(3);
    }
}

$set_bot_commands_request = Controller::setBotCommands($bot);
if ($set_bot_commands_request !== null) {
    $set_bot_commands_request->sendRequest();
}

if ($settings['chat_for_notifications'] !== null) {
    $bot->sendMessage($settings['chat_for_notifications'], 'TelegramPi was run')->sendRequest();
}

$update_id_max = null;
$is_first_loop = true;

while (true) {
    try {
        $date = date('Y.m.d H:i:s');
        echo "[$date] Getting updates\n";
        $updates = $bot->getUpdates($update_id_max === null ? null : $update_id_max + 1)->sendRequest();
    } catch (Throwable $exception) {
        printf(
            "%s #%d: %s\n",
            get_class($exception),
            $exception->getCode(),
            $exception->getMessage(),
        );

        if ($exception instanceof TypeError) {
            foreach ($bot->last_response_decoded['result'] as $update_raw) {
                if ($update_id_max === null || $update_id_max < $update_raw['update_id']) {
                    $update_id_max = $update_raw['update_id'];
                }
            }
        }

        sleep($settings['sleep_between_updates']);
        continue;
    }

    foreach ($updates as $update) {
        $update_id_max ??= $update->update_id;
        if ($update_id_max > $update->update_id) {
            continue;
        }

        $update_id_max = $update->update_id;

        if ($is_first_loop) {
            continue;
        }

        echo "\tUpdate {$update->update_id}: {$update->getType()?->value}\n";

        $input = new Input($settings, $bot, $update);
        $request = Controller::handle($input);

        if ($request !== null) {
            try {
                try {
                    $request->sendRequest(3);
                } catch (TelegramBotsApiException $api_exception) {
                    $sleep_seconds = $api_exception?->parameters?->retry_after;
                    if ($sleep_seconds !== null) {
                        echo "\tSleep $sleep_seconds sec.\n";
                        sleep($sleep_seconds);
                        $request->sendRequest();
                    }
                }
            } catch (Throwable $exception) {
                printf(
                    "%s #%d: %s\n",
                    get_class($exception),
                    $exception->getCode(),
                    $exception->getMessage(),
                );
            }
        }
    }

    $is_first_loop = false;
    sleep($settings['sleep_between_updates']);
}