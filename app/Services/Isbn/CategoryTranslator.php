<?php

namespace App\Services\Isbn;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

/**
 * Prevod eksternih naziva kategorija na srpski.
 *
 * Ako je naziv vec na srpskom (sadrzi cirilicu), vraca se nepromenjen. U
 * suprotnom se koristi besplatni Google Translate klijent (auto-detect izvora).
 * Pri neuspehu vraca se originalni (sirovi) naziv da unos ne bi pukao.
 */
class CategoryTranslator
{
    public function translate(?string $category): ?string
    {
        $category = trim((string) $category);

        if ($category === '') {
            return null;
        }

        if ($this->isSerbian($category) || ! (bool) config('isbn.translate.enabled', true)) {
            return $category;
        }

        $target = (string) config('isbn.translate.target', 'sr');
        $ttl = (int) config('isbn.translate.cache_ttl', 604800);

        return Cache::remember(
            'isbn:category:translation:'.md5($category.'|'.$target),
            $ttl,
            fn (): string => $this->request($category, $target),
        );
    }

    private function request(string $category, string $target): string
    {
        try {
            $translator = new GoogleTranslate($target, null, [
                'timeout' => (int) config('isbn.timeout', 8),
                'headers' => ['User-Agent' => (string) config('isbn.user_agent', '')],
            ]);

            if ($url = config('isbn.translate.url')) {
                $translator->setUrl((string) $url);
            }

            $translated = trim((string) $translator->translate($category));
        } catch (Throwable $exception) {
            Log::warning('ISBN category translation failed', [
                'category' => $category,
                'target' => $target,
                'error' => $exception->getMessage(),
            ]);

            return $category;
        }

        return $translated === '' ? $category : $translated;
    }

    private function isSerbian(string $value): bool
    {
        return preg_match('/\p{Cyrillic}/u', $value) === 1;
    }
}
