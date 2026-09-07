<?php

namespace App\Services\Matching;

/**
 * Turns job titles and resume text into a small set of canonical role terms
 * so a listing title can be compared with what a candidate has actually done.
 *
 * "Junior Programmer" and "Frontend Engineer Specialist" both reduce to
 * {developer}; "Attorney" reduces to {attorney}; seniority words, filler and
 * location words are dropped (config/matching.php 'role_stopwords'), and
 * synonyms collapse onto one key ('role_synonyms'). Comparison is then a
 * plain set intersection, which keeps the explanation honest: "your resume
 * mentions programmer / developer work".
 */
final class RoleVocabulary
{
    /** @return list<string> canonical terms, unique, in order of appearance */
    public static function terms(?string $text): array
    {
        $text = mb_strtolower(trim((string) $text));
        if ($text === '') {
            return [];
        }

        // Multi-word phrases first ("help desk", "software engineer"), so they
        // are recognised before the tokeniser splits them apart.
        foreach (config('matching.role_phrases', []) as $pattern => $canonical) {
            $text = preg_replace($pattern, " {$canonical} ", $text);
        }

        $stopwords = array_flip(config('matching.role_stopwords', []));
        $synonyms = self::synonymIndex();
        $terms = [];

        foreach (preg_split('/[^a-z0-9+#]+/u', $text) ?: [] as $token) {
            if ($token === '' || mb_strlen($token) < 3 || isset($stopwords[$token]) || is_numeric($token)) {
                continue;
            }

            $canonical = $synonyms[$token] ?? $synonyms[self::stem($token)] ?? null;
            if ($canonical === null) {
                $canonical = self::stem($token);
                if (mb_strlen($canonical) < 3) {
                    continue;
                }
            }

            $terms[$canonical] = true;
        }

        return array_keys($terms);
    }

    /** True when the two term sets share at least one role term. */
    public static function overlap(array $a, array $b): array
    {
        return array_values(array_intersect($a, $b));
    }

    /** Very light stemming: plurals and common verb endings only. */
    private static function stem(string $token): string
    {
        return preg_replace('/(ings?|ers?|ed|s)$/', '', $token) ?: $token;
    }

    /** @return array<string, string> variant => canonical, built once per request */
    private static function synonymIndex(): array
    {
        static $index = null;

        if ($index === null) {
            $index = [];
            foreach (config('matching.role_synonyms', []) as $canonical => $variants) {
                $index[$canonical] = $canonical;
                $index[self::stem($canonical)] = $canonical;
                foreach ($variants as $variant) {
                    $index[$variant] = $canonical;
                    $index[self::stem($variant)] = $canonical;
                }
            }
        }

        return $index;
    }
}
