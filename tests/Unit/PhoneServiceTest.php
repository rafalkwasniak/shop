<?php

namespace Tests\Unit;

use App\Services\PhoneService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneServiceTest extends TestCase
{
    private PhoneService $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->phone = new PhoneService;
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function normalizeCases(): array
    {
        return [
            'null'                 => [null, null],
            'puste'                => ['', null],
            'goły 9-cyfrowy'       => ['500600700', '48500600700'],
            'ze spacjami'          => ['500 600 700', '48500600700'],
            'z prefiksem +48'      => ['+48 500 600 700', '48500600700'],
            'z prefiksem 48'       => ['48500600700', '48500600700'],
            'z zerem wiodącym'     => ['0500600700', '48500600700'],
            'ze śmieciowymi znakami' => ['tel: (500)-600-700', '48500600700'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalizes_common_shapes_to_canonical(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->phone->normalize($input));
    }

    public function test_normalization_is_idempotent(): void
    {
        foreach (['500600700', '+48 500 600 700', '0500600700', '48123456789012345'] as $input) {
            $once = $this->phone->normalize($input);
            $twice = $this->phone->normalize($once);

            // Ponowny przebieg nie może dokładać kolejnego „48".
            $this->assertSame($once, $twice, "Niestabilna normalizacja dla: {$input}");
        }
    }

    public function test_does_not_stack_country_code_on_repeated_runs(): void
    {
        // Regresja: przy edycji niepoprawnego numeru rosło „48484848…".
        // Po pierwszym przebiegu długość musi się ustabilizować (nie rośnie).
        $first = $this->phone->normalize('48485564453341');
        $value = $first;

        for ($i = 0; $i < 5; $i++) {
            $value = $this->phone->normalize($value);
            $this->assertSame($first, $value, 'Normalizacja rośnie przy powtórzeniu.');
        }
    }

    public function test_formats_valid_core_for_display(): void
    {
        $this->assertSame('+48 500 600 700', $this->phone->format('500600700'));
    }

    public function test_href_is_international_with_plus(): void
    {
        // Regresja: link „tel:48500600700" telefon wybierał jako numer krajowy.
        $this->assertSame('+48500600700', $this->phone->href('48500600700'));
        $this->assertSame('+48500600700', $this->phone->href('500 600 700'));
        $this->assertNull($this->phone->href(null));
    }
}
