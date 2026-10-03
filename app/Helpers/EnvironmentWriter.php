<?php

namespace App\Helpers;

class EnvironmentWriter
{
    /**
     * Escapes an environment value by looking for any characters that could
     * reasonably cause environment parsing issues. Those values are then wrapped
     * in quotes before being returned.
     */
    public static function escapeEnvironmentValue(?string $value = null): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value === '' || preg_match('/([^\w.\-+\/])+/', $value)) {
            return '"'.strtr($value, [
                '\\' => '\\\\',
                '"' => '\\"',
                '$' => '\\$',
                "\r" => '\r',
                "\n" => '\n',
            ]).'"';
        }

        return $value;
    }

    /**
     * Update the .env file for the application using the passed in values.
     *
     * @throws \Exception
     */
    public static function write(array $values = []): void
    {
        $path = app()->environmentFilePath();

        if (! file_exists($path)) {
            throw new \Exception('Cannot locate .env file, was this software installed correctly?');
        }

        $saveContents = file_get_contents($path);
        collect($values)->each(function ($value, $key) use (&$saveContents) {
            $key = strtoupper($key);
            $saveValue = sprintf('%s=%s', $key, self::escapeEnvironmentValue($value));

            if (preg_match_all('/^'.$key.'=(.*)$/m', $saveContents) < 1) {
                $saveContents = $saveContents.PHP_EOL.$saveValue;
            } else {
                $saveContents = preg_replace_callback('/^'.preg_quote($key, '/').'=(.*)$/m', static fn (): string => $saveValue, $saveContents);
            }
        });

        file_put_contents($path, $saveContents);
    }
}
