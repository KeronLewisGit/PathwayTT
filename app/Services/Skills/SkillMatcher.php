<?php

namespace App\Services\Skills;

use App\Models\Skill;

/**
 * Alias-aware skill detection in free text, with the taxonomy compiled once
 * per process so it can be applied to thousands of job descriptions in a
 * single sync run without re-querying.
 */
class SkillMatcher
{
    /** @var list<array{id:int,name:string,patterns:list<string>}>|null */
    private ?array $compiled = null;

    /**
     * @return array<int, string> skill id => canonical name, in order found
     */
    public function match(?string $text): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        $found = [];
        foreach ($this->compiled() as $skill) {
            foreach ($skill['patterns'] as $pattern) {
                if (preg_match($pattern, $text)) {
                    $found[$skill['id']] = $skill['name'];
                    break;
                }
            }
        }

        return $found;
    }

    /** Resolve a list of short terms (tags) to canonical skills. */
    public function matchTerms(array $terms): array
    {
        $found = [];
        foreach ($terms as $term) {
            $found += $this->match((string) $term);
        }

        return $found;
    }

    private function compiled(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        $this->compiled = [];
        foreach (Skill::query()->get(['id', 'name', 'slug', 'aliases']) as $skill) {
            $patterns = [];
            foreach ($skill->matchTerms() as $term) {
                // Word-ish boundaries that survive "C#", "C++", ".NET":
                // no letter/digit may directly touch the term.
                $patterns[] = '/(?<![A-Za-z0-9])'.preg_quote($term, '/').'(?![A-Za-z0-9+#])/iu';
            }
            $this->compiled[] = ['id' => (int) $skill->id, 'name' => $skill->name, 'patterns' => $patterns];
        }

        return $this->compiled;
    }

    public function forget(): void
    {
        $this->compiled = null;
    }
}
