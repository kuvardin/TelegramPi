<?php

declare(strict_types=1);

namespace Kuvardin\TelegramPi;

use Kuvardin\TelegramBotsApi\Bot;
use Kuvardin\TelegramBotsApi\Enums\ParseMode;
use Kuvardin\TelegramBotsApi\Request;
use Kuvardin\TelegramBotsApi\Types\BotCommand;
use Throwable;

/**
 * @author Maxim Kuvardin <maxim@kuvard.in>
 * @package Kuvardin\TelegramPi
 */
class Controller
{
    private function __construct()
    {
    }

    public static function handle(Input $input): ?Request
    {
        if ($input->update->message !== null) {
            $message = $input->update->message;
            $command = self::getCommandName($input);
            if ($command !== null) {
                [$command_name, $command_data] = $command;
                $command_class = self::getCommandClass($command_name);
                if (class_exists($command_class)) {
                    try {
                        return $command_class::handle($input, $command_data);
                    } catch (Throwable $exception) {
                        return $input->bot->sendMessage(
                            $message->chat->id,
                            sprintf(
                                '%s %d: %s',
                                get_class($exception),
                                $exception->getCode(),
                                Bot::filterString($exception->getMessage()),
                            ),
                            parse_mode: ParseMode::HTML,
                            reply_to_message_id: $message->message_id,
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return AbstractCommand[]|string[]
     */
    public static function getCommands(): array
    {
        $result = [];
        $files = scandir(ROOT_DIR . '/src/Commands');
        foreach ($files as $file_path) {
            if (is_dir($file_path)) {
                continue;
            }

            if (preg_match('|^([A-Za-z0-9_]+)\.php$|', $file_path, $match)) {
                $result[] = self::getCommandClass($match[1]);
            }
        }

        return $result;
    }

    public static function setBotCommands(Bot $bot): ?Request
    {
        $command_classes = self::getCommands();
        if ($command_classes === []) {
            return null;
        }

        $commands = [];
        foreach ($command_classes as $command_class) {
            $telegram_command_name = $command_class::getTelegramCommandName();
            $commands[] = new BotCommand($telegram_command_name, $command_class::getDescription());
        }

        return $bot->setMyCommands($commands);

    }

    private static function getCommandClass(string $command_name): string|AbstractCommand
    {
        return "Kuvardin\\TelegramPi\\Commands\\$command_name";
    }

    private static function getCommandName(Input $input): ?array
    {
        if ($input->update->message === null) {
            return null;
        }

        $message = $input->update->message;

        $text = $message->text ?? $message->caption;
        if ($text === null) {
            return null;
        }

        $is_from_admin = in_array($message->chat->id, $input->settings['admin_ids'], true) ||
            ($message->from !== null && in_array($message->from->id, $input->settings['admin_ids'], true));
        if ($input->settings['only_for_administrators'] && !$is_from_admin) {
            return null;
        }

        if (!preg_match('|^/([a-zA-Z][a-zA-Z0-9_]+)(.*?)$|sui', $text, $match)) {
            return null;
        }

        $command_name = '';
        $command_name_parts = explode('_', $match[1]);
        foreach ($command_name_parts as $command_name_part) {
            $command_name .= ucfirst(strtolower($command_name_part));
        }

        $command_data = trim($match[2]);
        if ($command_data === '') {
            $command_data = null;
        }

        return [$command_name, $command_data];
    }

}