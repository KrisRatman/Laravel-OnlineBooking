<?php

if (! function_exists('money_rub')) {
    /** Копейки → «1 500 ₽». */
    function money_rub(int $kopecks): string
    {
        $rubles = $kopecks / 100;
        $decimals = $kopecks % 100 === 0 ? 0 : 2;

        return number_format($rubles, $decimals, ',', ' ').' ₽';
    }
}
