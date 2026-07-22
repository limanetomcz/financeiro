<?php

namespace Tests\Unit;

use App\Bancario\Support\CalculoDvModulo11;
use PHPUnit\Framework\TestCase;

class CalculoDvModulo11Test extends TestCase
{
    public function test_digito_deterministico(): void
    {
        $dv = CalculoDvModulo11::digito('0101001234526000001');

        $this->assertMatchesRegularExpression('/^[0-9]$/', $dv);
        $this->assertSame($dv, CalculoDvModulo11::digito('0101001234526000001'));
    }
}
