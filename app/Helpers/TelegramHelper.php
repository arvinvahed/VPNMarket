<?php

if (!function_exists('escapeTelegramHTML')) {
    function escapeTelegramHTML(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $allowedTags = ['b', 'i', 'a', 'code', 'pre'];
        foreach ($allowedTags as $tag) {
            $text = str_replace(
                ['&lt;' . $tag . '&gt;', '&lt;/' . $tag . '&gt;'],
                ["<$tag>", "</$tag>"],
                $text
            );
        }
        return $text;
    }
}
