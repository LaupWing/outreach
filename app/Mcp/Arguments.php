<?php

namespace App\Mcp;

/**
 * Some clients send an object argument as a JSON string; take both.
 */
class Arguments
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys  Dot paths are not supported; top-level keys and "leads.*.key" shapes are handled by the caller.
     * @return array<string, mixed>
     */
    public static function decodeObjects(array $arguments, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($arguments[$key]) && is_string($arguments[$key])) {
                $decoded = json_decode($arguments[$key], true);

                if (is_array($decoded)) {
                    $arguments[$key] = $decoded;
                }
            }
        }

        return $arguments;
    }

    /**
     * The same for every row of a list argument, e.g. leads[*].facts.
     *
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function decodeObjectsIn(array $arguments, string $list, array $keys): array
    {
        if (is_string($arguments[$list] ?? null)) {
            $decoded = json_decode($arguments[$list], true);

            if (is_array($decoded)) {
                $arguments[$list] = $decoded;
            }
        }

        if (is_array($arguments[$list] ?? null)) {
            foreach ($arguments[$list] as $index => $row) {
                if (is_array($row)) {
                    $arguments[$list][$index] = self::decodeObjects($row, $keys);
                }
            }
        }

        return $arguments;
    }
}
