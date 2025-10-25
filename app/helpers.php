
<?php

if (!function_exists('escapeTelegramHTML')) {

    function escapeTelegramHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
