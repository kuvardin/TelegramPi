<?php

declare(strict_types=1);

namespace Kuvardin\TelegramPi;

use Kuvardin\TelegramBotsApi\Bot;
use Kuvardin\TelegramBotsApi\Types\Update;

/**
 * @author Maxim Kuvardin <maxim@kuvard.in>
 * @package Kuvardin\TelegramPi
 */
class Input
{
    readonly array $settings;
    readonly Bot $bot;
    readonly Update $update;

    public function __construct(array $settings, Bot $bot, Update $update)
    {
        $this->settings = $settings;
        $this->bot = $bot;
        $this->update = $update;
    }
}