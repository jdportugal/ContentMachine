<?php

namespace App\Services\News;

class NewsManager
{
    public function fontes(): array
    {
        return config('contentmachine.news.fontes', []);
    }

    public function driver(): NewsDriver
    {
        return match (config('contentmachine.news.driver', 'fake')) {
            'api' => new ApiNewsDriver,
            default => new FakeNewsDriver,
        };
    }

    public function relatorio(?array $fontes = null): array
    {
        return $this->driver()->relatorio($fontes ?? $this->fontes());
    }
}
