<?php

namespace Tests\Feature;

use App\Services\DiscordErrorReporter;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DiscordErrorReportingTest extends TestCase
{
    public function test_reports_an_exception_to_the_webhook(): void
    {
        config(['services.discord.webhook' => 'https://discord.test/webhook']);
        Http::fake();

        app(DiscordErrorReporter::class)->report(new RuntimeException('Something broke'));

        Http::assertSent(function ($request) {
            $embed = $request['embeds'][0];

            return $request->url() === 'https://discord.test/webhook'
                && str_contains($embed['title'], config('app.name'))
                && str_contains($embed['description'], 'Something broke')
                && collect($embed['fields'])->contains(
                    fn ($field) => $field['name'] === 'Type' && str_contains($field['value'], 'RuntimeException')
                );
        });
    }

    public function test_does_nothing_without_a_configured_webhook(): void
    {
        config(['services.discord.webhook' => null]);
        Http::fake();

        app(DiscordErrorReporter::class)->report(new RuntimeException('x'));

        Http::assertNothingSent();
    }

    public function test_a_delivery_failure_is_swallowed(): void
    {
        config(['services.discord.webhook' => 'https://discord.test/webhook']);
        Http::fake(fn () => throw new \Exception('connection down'));

        // Must not throw — error reporting can never break the request.
        app(DiscordErrorReporter::class)->report(new RuntimeException('x'));

        $this->assertTrue(true);
    }
}
