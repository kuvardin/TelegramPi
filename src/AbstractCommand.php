<?php

declare(strict_types=1);

namespace Kuvardin\TelegramPi;

use Kuvardin\TelegramBotsApi\Request;

/**
 * @author Maxim Kuvardin <maxim@kuvard.in>
 * @package Kuvardin\TelegramPi
 */
abstract class AbstractCommand
{
    final private function __construct()
    {
    }

    final public static function getTelegramCommandName(): string
    {
        $class_name_parts = explode('\\', static::class);
        $class_name = array_pop($class_name_parts);
        $command_name = preg_replace_callback('|[A-Z]|', static fn($match) => '_' . strtolower($match[0]), $class_name);
        return ltrim($command_name, '_');
    }

    /**
     * @return string Command description
     */
    abstract public static function getDescription(): string;

    /**
     * @param Input $input
     * @param string|null $data
     * @return Request|null
     */
    abstract public static function handle(Input $input, ?string $data): ?Request;
}