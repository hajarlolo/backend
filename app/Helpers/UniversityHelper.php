<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class UniversityHelper
{
    private const MAX_RESULTS = 12;

    /**
     * @var array<int, array{
     *     id:int,
     *     name:string,
     *     normalized_name:string,
     *     aliases:array<int, string>,
     *     tokens:array<int, string>
     * }>
     */
    private array $index;

    public function __construct()
    {
        $this->index = $this->buildIndex(config('universities', []));
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function search(string $term): array
    {
        $term = $this->normalize($term);

        if ($term === '') {
            return array_map(
                static fn (array $item): array => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                ],
                array_slice($this->index, 0, self::MAX_RESULTS)
            );
        }

        $scored = [];

        foreach ($this->index as $item) {
            $score = $this->score($term, $item);

            if ($score < $this->minimumScore($term)) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'id' => $item['id'],
                'name' => $item['name'],
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return strcasecmp($a['name'], $b['name']);
            }

            return $b['score'] <=> $a['score'];
        });

        return array_map(
            static fn (array $item): array => [
                'id' => $item['id'],
                'name' => $item['name'],
            ],
            array_slice($scored, 0, self::MAX_RESULTS)
        );
    }

    private function minimumScore(string $term): int
    {
        $length = strlen($term);

        if ($length <= 2) {
            return 220;
        }

        if ($length <= 4) {
            return 320;
        }

        return 260;
    }

    /**
     * @param  array<int|string, string>  $universities
     * @return array<int, array{
     *     id:int,
     *     name:string,
     *     normalized_name:string,
     *     aliases:array<int, string>,
     *     tokens:array<int, string>
     * }>
     */
    private function buildIndex(array $universities): array
    {
        $index = [];

        foreach ($universities as $id => $name) {
            $normalizedName = $this->normalize($name);
            $aliases = $this->extractAliases($name);

            $normalizedAliases = array_values(array_unique(array_filter(
                array_map(fn (string $value): string => $this->normalize($value), $aliases)
            )));

            $tokens = array_values(array_unique(array_filter(explode(' ', $normalizedName))));
            foreach ($normalizedAliases as $alias) {
                $tokens = array_values(array_unique([
                    ...$tokens,
                    ...array_filter(explode(' ', $alias)),
                ]));
            }

            $index[] = [
                'id' => (int) $id,
                'name' => $name,
                'normalized_name' => $normalizedName,
                'aliases' => $normalizedAliases,
                'tokens' => $tokens,
            ];
        }

        usort($index, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $index;
    }

    /**
     * @param  array{
     *     normalized_name:string,
     *     aliases:array<int, string>,
     *     tokens:array<int, string>
     * }  $item
     */
    private function score(string $term, array $item): int
    {
        $score = 0;
        $name = $item['normalized_name'];
        $termTokens = array_values(array_filter(explode(' ', $term)));

        if ($term === $name) {
            $score += 1200;
        }

        if (str_starts_with($name, $term)) {
            $score += 700;
        }

        $position = strpos($name, $term);
        if ($position !== false) {
            $score += 500 - min((int) $position, 200);
        }

        foreach ($item['aliases'] as $alias) {
            if ($alias === $term) {
                $score += 1100;
            }

            if (str_starts_with($alias, $term)) {
                $score += 900;
            }

            if (str_contains($alias, $term)) {
                $score += 700;
            }

            if (strlen($term) >= 3 && strlen($alias) >= 3) {
                $distance = levenshtein($term, $alias);
                if ($distance <= 2) {
                    $score += 320 - ($distance * 80);
                }
            }
        }

        if ($termTokens !== []) {
            $matchedTokens = 0;

            foreach ($termTokens as $token) {
                foreach ($item['tokens'] as $candidateToken) {
                    if ($candidateToken === $token || str_starts_with($candidateToken, $token)) {
                        $matchedTokens++;
                        break;
                    }
                }
            }

            if ($matchedTokens === count($termTokens)) {
                $score += 300 + (count($termTokens) * 25);
            } else {
                $score += $matchedTokens * 80;
            }
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function extractAliases(string $name): array
    {
        $aliases = [];

        if (preg_match_all('/\(([^)]+)\)/', $name, $matches)) {
            foreach ($matches[1] as $value) {
                $aliases[] = trim($value);

                if (str_contains($value, '-')) {
                    $aliases[] = trim((string) Str::before($value, '-'));
                }
            }
        }

        if (preg_match_all('/\b([A-Z0-9]{2,10})\b/u', Str::upper($name), $acronymMatches)) {
            foreach ($acronymMatches[1] as $value) {
                $aliases[] = trim($value);
            }
        }

        return array_values(array_unique(array_filter($aliases)));
    }

    private function normalize(string $value): string
    {
        $value = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        return $value;
    }
}
