<?php

namespace App\Services\Clips\Contracts;

interface TranscriptionService
{
    /**
     * Transcribe an audio file into a timestamped transcript.
     *
     * @return array{duration:float,text:string,words:array,segments:array}
     */
    public function transcribe(string $audioPath): array;
}
