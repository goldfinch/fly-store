<?php

if (! function_exists('json_validate')) {

    function json_validate(string $string): bool {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

