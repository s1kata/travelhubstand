<?php
/**
 * Фильтр мата в отзывах (серверная валидация).
 */
declare(strict_types=1);

/** @return string[] */
function review_profanity_stop_words(): array
{
    return [
        'залуп', 'хуй', 'хуе', 'хуи', 'пизд', 'пидор', 'пидар', 'ебан', 'ебат', 'ебл', 'ебать', 'бляд', 'блять', 'бля',
        'сука', 'суки', 'мудак', 'мудил', 'гандон', 'шлюх', 'педераст', 'педик',
        'fuck', 'shit', 'bitch', 'asshole', 'cunt', 'dick', 'whore', 'slut',
    ];
}

function review_text_contains_profanity(string $text): bool
{
    $lower = mb_strtolower(trim($text));
    if ($lower === '') {
        return false;
    }
    foreach (review_profanity_stop_words() as $word) {
        if ($word !== '' && mb_strpos($lower, $word) !== false) {
            return true;
        }
    }

    return false;
}
