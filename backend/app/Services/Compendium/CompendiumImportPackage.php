<?php

namespace App\Services\Compendium;

use RuntimeException;
use ZipArchive;

final class CompendiumImportPackage
{
    private $zip;
    private $path;

    public function __construct(string $path)
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            throw new RuntimeException('Compendium package was not found: ' . $path);
        }
        $zip = new ZipArchive();
        if ($zip->open($real) !== true) {
            throw new RuntimeException('Compendium package could not be opened: ' . $path);
        }
        $this->zip = $zip;
        $this->path = $real;
    }

    public function __destruct()
    {
        if ($this->zip instanceof ZipArchive) {
            $this->zip->close();
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function checksum(): string
    {
        return hash_file('sha256', $this->path);
    }

    public function json(string $suffix): array
    {
        $stream = $this->stream($suffix);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $decoded = json_decode((string) $contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON in package entry: ' . $suffix);
        }
        return $decoded;
    }

    /** @return resource */
    public function stream(string $suffix)
    {
        $name = $this->entryName($suffix);
        $stream = $this->zip->getStream($name);
        if (!is_resource($stream)) {
            throw new RuntimeException('Package entry could not be read: ' . $suffix);
        }
        return $stream;
    }

    public function has(string $suffix): bool
    {
        return $this->findEntryName($suffix) !== null;
    }

    /** @return array<int,string> */
    public function entries(string $directorySuffix): array
    {
        $needle = trim($directorySuffix, '/') . '/';
        $results = [];
        for ($index = 0; $index < $this->zip->numFiles; $index++) {
            $name = (string) $this->zip->getNameIndex($index);
            if (strpos($name, $needle) !== false && substr($name, -1) !== '/') {
                $results[] = $name;
            }
        }
        sort($results);
        return $results;
    }

    public function jsonByName(string $name): array
    {
        $stream = $this->zip->getStream($name);
        if (!is_resource($stream)) {
            throw new RuntimeException('Package entry could not be read: ' . $name);
        }
        $decoded = json_decode((string) stream_get_contents($stream), true);
        fclose($stream);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON in package entry: ' . $name);
        }
        return $decoded;
    }

    private function entryName(string $suffix): string
    {
        $name = $this->findEntryName($suffix);
        if ($name === null) {
            throw new RuntimeException('Required package entry is missing: ' . $suffix);
        }
        return $name;
    }

    private function findEntryName(string $suffix): ?string
    {
        $suffix = ltrim($suffix, '/');
        for ($index = 0; $index < $this->zip->numFiles; $index++) {
            $name = (string) $this->zip->getNameIndex($index);
            if ($name === $suffix || substr($name, -strlen('/' . $suffix)) === '/' . $suffix) {
                return $name;
            }
        }
        return null;
    }
}
