<?php

namespace FastQL\Tests\Utils;

use Symfony\Component\Yaml\Yaml;

final class DataSet
{
    /**
     * Load data set from file or collection of files.
     */
    public static function load($filenames): iterable
    {
        if (!is_array($filenames)) {
            $filenames = [$filenames];
        }
        foreach ($filenames as $filename) {
            switch (pathinfo($filename, PATHINFO_EXTENSION)) {
                case 'csv':
                    yield pathinfo($filename, PATHINFO_FILENAME) => Csv::load($filename, true);
                    break;
                case 'json':
                    $json = json_decode(file_get_contents($filename), true);
                    foreach ($json as $key => $collection) {
                        yield $key => $collection;
                    }
                    break;
                case 'php':
                    $php = (include $filename);
                    foreach ($php as $key => $collection) {
                        yield $key => $collection;
                    }
                    break;
                case 'yaml':
                    $yaml = Yaml::parseFile($filename);
                    foreach ($yaml as $key => $collection) {
                        yield $key => $collection;
                    }
                    break;
            }
        }
    }

    /**
     * Load data set from directory.
     */
    public static function loadDir(string $dirname): iterable
    {
        return self::load(glob("$dirname/*."));
    }

    private function __construct()
    {
    }
}