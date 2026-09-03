<?php

namespace App\Services\JobSources\Support;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Cache;

/**
 * Minimal robots.txt check for the HTML crawlers: fetches /robots.txt once
 * per host (cached), then applies the most specific matching group
 * (our own user-agent token, else "*") with longest-match Allow/Disallow.
 * A missing or unreadable robots.txt is treated as "allowed".
 */
class RobotsTxt
{
    public function __construct(private readonly Http $http) {}

    public function allows(string $url, string $userAgentToken = 'PathwayTT'): bool
    {
        $parts = parse_url($url);
        if (! isset($parts['host'])) {
            return true;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        $rules = $this->rules("{$scheme}://{$parts['host']}", $userAgentToken);

        $decision = true;
        $longest = -1;
        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '' || ! self::matches($pattern, $path) || strlen($pattern) < $longest) {
                continue;
            }
            $longest = strlen($pattern);
            $decision = $type === 'allow';
        }

        return $decision;
    }

    /** @return list<array{0:string,1:string}> [type, pattern] for the applicable group */
    private function rules(string $origin, string $token): array
    {
        $body = Cache::remember('robots:'.md5($origin), 3600, function () use ($origin) {
            try {
                $response = $this->http->timeout(10)->get("{$origin}/robots.txt");

                return $response->successful() ? (string) $response->body() : '';
            } catch (\Throwable) {
                return '';
            }
        });

        $groups = [];
        $currentAgents = [];
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $currentAgents[] = strtolower($value);
                continue;
            }
            if (in_array($field, ['allow', 'disallow'], true)) {
                foreach ($currentAgents as $agent) {
                    $groups[$agent][] = [$field, $value];
                }
            }
            if (! in_array($field, ['allow', 'disallow'], true)) {
                // any other directive ends the user-agent run for the next group header
            }
        }

        $token = strtolower($token);
        foreach (array_keys($groups) as $agent) {
            if ($agent !== '*' && str_contains($token, $agent)) {
                return $groups[$agent];
            }
        }

        return $groups['*'] ?? [];
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $regex = '^'.implode('.*', array_map(fn ($p) => preg_quote($p, '~'), explode('*', rtrim($pattern, '$'))));

        return (bool) preg_match('~'.$regex.($anchored ? '$' : '').'~', $path);
    }
}
