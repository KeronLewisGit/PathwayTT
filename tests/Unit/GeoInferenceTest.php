<?php

use App\Services\JobSources\Support\GeoInference;

test('worldwide wording is open to everyone', function (string $text) {
    expect(GeoInference::infer($text))->toBe(['geo' => 'worldwide', 'country' => null, 'open' => true]);
})->with(['Worldwide', 'Anywhere', 'Global, fully remote', 'Remote - Anywhere', 'International']);

test('a single country other than T&T is country-restricted and closed', function () {
    expect(GeoInference::infer('USA'))->toBe(['geo' => 'country_restricted', 'country' => 'US', 'open' => false])
        ->and(GeoInference::infer('United States'))->toMatchArray(['country' => 'US', 'open' => false])
        ->and(GeoInference::infer('US only'))->toMatchArray(['country' => 'US'])
        ->and(GeoInference::infer('Canada'))->toMatchArray(['geo' => 'country_restricted', 'country' => 'CA'])
        ->and(GeoInference::infer(['United States']))->toMatchArray(['country' => 'US', 'open' => false]);
});

test('regions that include the Caribbean are open, others are closed', function () {
    expect(GeoInference::infer('LATAM, Europe, USA, Canada, APAC'))->toBe(['geo' => 'region_restricted', 'country' => null, 'open' => true])
        ->and(GeoInference::infer('Americas'))->toMatchArray(['open' => true])
        ->and(GeoInference::infer('Caribbean'))->toMatchArray(['open' => true])
        ->and(GeoInference::infer('Trinidad and Tobago'))->toBe(['geo' => 'region_restricted', 'country' => 'TT', 'open' => true])
        ->and(GeoInference::infer('Europe'))->toBe(['geo' => 'region_restricted', 'country' => null, 'open' => false])
        ->and(GeoInference::infer('USA, Canada, USA timezones'))->toBe(['geo' => 'region_restricted', 'country' => null, 'open' => false])
        ->and(GeoInference::infer('Europe, EMEA, UK, Germany, France, European timezones'))->toMatchArray(['open' => false]);
});

test('unrecognised text is left as not stated rather than guessed', function () {
    expect(GeoInference::infer('Goa'))->toBe(['geo' => null, 'country' => null, 'open' => null])
        ->and(GeoInference::infer(''))->toBe(['geo' => null, 'country' => null, 'open' => null])
        ->and(GeoInference::infer('Remote'))->toBe(['geo' => null, 'country' => null, 'open' => null])
        ->and(GeoInference::infer(null))->toBe(['geo' => null, 'country' => null, 'open' => null]);
});

test('short country codes do not fire inside ordinary words', function () {
    // "us" alone is deliberately not a country phrase ("join us"); "uk" is.
    expect(GeoInference::infer('Come join us on a global mission'))->toMatchArray(['geo' => 'worldwide'])
        ->and(GeoInference::infer('Join us'))->toBe(['geo' => null, 'country' => null, 'open' => null])
        ->and(GeoInference::infer('UK'))->toMatchArray(['country' => 'GB']);
});

test('timezone windows are checked against AST (UTC-4)', function () {
    expect(GeoInference::timezoneAllowsAst([]))->toBeNull()
        ->and(GeoInference::timezoneAllowsAst([-8, -7, -6, -5, -4, -3]))->toBeTrue()
        ->and(GeoInference::timezoneAllowsAst([-10, -9, -8, -7, -6, -5, 14]))->toBeFalse()
        ->and(GeoInference::timezoneAllowsAst(['-4']))->toBeTrue();
});
