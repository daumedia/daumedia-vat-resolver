<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Tests\Unit\Vies;

use Daumedia\VatResolver\Vies\InMemoryViesValidator;
use Daumedia\VatResolver\Vies\NullViesValidator;
use PHPUnit\Framework\TestCase;

final class NullViesValidatorTest extends TestCase
{
    public function testItKnowsNothingAboutAnyNumber(): void
    {
        $validator = new NullViesValidator();

        self::assertNull($validator->check('DE', 'DE123456789'));
        self::assertNull($validator->check('LU', 'LU12345678'));
        self::assertNull($validator->check('XX', ''));
    }

    public function testTheInMemoryValidatorAnswersOnlyWhatItWasTold(): void
    {
        $validator = new InMemoryViesValidator(['DE123456789' => true, 'FR12345678901' => false]);

        self::assertTrue($validator->check('DE', 'DE123456789')?->valid);
        self::assertFalse($validator->check('FR', 'FR12345678901')?->valid);
        self::assertNull($validator->check('IT', 'IT12345678901'), 'Nicht hinterlegt heisst unbekannt.');
    }

    public function testTheInMemoryValidatorIgnoresFormattingLikeTheRealOne(): void
    {
        $validator = new InMemoryViesValidator(['DE123456789' => true]);

        self::assertTrue($validator->check('DE', 'de 123.456.789')?->valid);
    }

    public function testAConfirmedAnswerCarriesAConsultationNumber(): void
    {
        $validator = new InMemoryViesValidator(['DE123456789' => true]);

        self::assertNotNull($validator->check('DE', 'DE123456789')?->consultationNumber);
    }
}
