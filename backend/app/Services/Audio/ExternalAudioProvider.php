<?php

namespace App\Services\Audio;

interface ExternalAudioProvider
{
    public function name(): string;

    public function supports(string $url): bool;

    /** @return array{provider:string,reference:string,url:string,thumbnailUrl:?string} */
    public function normalize(string $url): array;
}
