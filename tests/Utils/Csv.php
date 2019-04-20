<?php

namespace FastQL\Tests\Utils;

final class Csv
{
    private function __construct()
    {
    }

    public static function parse(string $csv, bool $assoc = false)
    {
        $data = [];
        $ctor = $assoc ? 'toArray' : 'toObject';
        $indices = [];
        foreach (preg_split("/(\r\n|\n|\r)/", $csv) as $line) {
            $entry = str_getcsv($line);
            if (empty($entry)) {
                continue;
            } else if (empty($indices)) {
                $indices = $entry;
            } else {
                $data[] = self::$ctor($indices, $entry);
            }
        }
        return $data;
    }

    public static function load(string $filename, bool $assoc = false): array
    {
        $data = [];
        $ctor = $assoc ? 'toArray' : 'toObject';
        $newSet = true;
        $indices = [];
        if ($file = fopen($filename, "r")) {
            while (false !== $entry = fgetcsv($file, 1000, ",")) {
                if (empty($entry)) {
                    continue;
                } else if (empty($indices)) {
                    $indices = $entry;
                } else {
                    $data[] = self::$ctor($indices, $entry);
                }
            }
            fclose($file);
        }
        return $data;
    }

    private static function toObject(array $indices, array $values): \stdClass
    {
        $obj = new \stdClass();
        foreach ($indices as $i => $index) {
            $obj->$index = !empty($values[$i]) ? $values[$i] : null;
        }
        return $obj;
    }

    private static function toArray(array $indices, array $values): array
    {
        $arr = [];
        foreach ($indices as $i => $index) {
            $arr[$index] = !empty($values[$i]) ? $values[$i] : null;
        }
        return $arr;
    }
}
