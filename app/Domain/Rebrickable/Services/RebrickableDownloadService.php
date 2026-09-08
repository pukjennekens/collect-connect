<?php

declare(strict_types=1);

namespace App\Domain\Rebrickable\Services;

use App\Domain\Rebrickable\Contracts\RebrickableDownloader;
use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class RebrickableDownloadService implements RebrickableDownloader
{
    public function retrieveRebrickableDataFromUrl(string $url): Generator
    {
        $temporary = tmpfile();
        if ($temporary === false) {
            throw new RuntimeException('Cannot create import download file.');
        }
        $zip = new ZipArchive;
        $stream = null;
        $opened = false;
        try {
            $path = stream_get_meta_data($temporary)['uri'] ?? null;
            if (! is_string($path)) {
                throw new RuntimeException('Missing temporary download path.');
            }
            Http::connectTimeout(15)->timeout(600)->withOptions(['sink' => $path])->get($url)->throw();
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Invalid Rebrickable ZIP archive.');
            }
            $opened = true;
            $name = $zip->getNameIndex(0);
            if ($zip->numFiles !== 1 || ! $name || ! str_ends_with($name, '.csv')) {
                throw new RuntimeException('Expected one CSV dataset in archive.');
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                throw new RuntimeException('Cannot read archived CSV.');
            }
            $header = fgetcsv($stream, escape: '');
            if (! $header || in_array(null, $header, true) || in_array('', $header, true) || count(array_unique($header)) !== count($header)) {
                throw new RuntimeException('Invalid CSV header.');
            }
            while (($row = fgetcsv($stream, escape: '')) !== false) {
                if (count($row) !== count($header)) {
                    throw new RuntimeException('CSV row does not match header.');
                }
                yield array_combine($header, $row);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($opened) {
                $zip->close();
            }
            fclose($temporary);
        }
    }
}
