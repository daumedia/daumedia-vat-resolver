<?php

declare(strict_types=1);

namespace Daumedia\VatResolver;

use Daumedia\VatResolver\Enum\CustomerType;
use Daumedia\VatResolver\Enum\RateCategory;
use Daumedia\VatResolver\Enum\ServiceType;
use Daumedia\VatResolver\Exception\UnsupportedServiceTypeException;
use Daumedia\VatResolver\Rates\RateTable;
use Daumedia\VatResolver\Vies\VatNumberFormat;
use Daumedia\VatResolver\Vies\ViesCheck;
use Daumedia\VatResolver\Vies\ViesValidatorInterface;

/**
 * Decides the VAT rate for one supply.
 *
 * $homeCountry is a constructor argument rather than a line in the rate table on
 * purpose: the table states facts about the world (what Finland charges), the
 * home country states a fact about whoever is using this library. Mixing them
 * would mean every rate update touches the user's identity, and a second company
 * could not use the package without forking the YAML.
 *
 * The rule order below is fixed and each step is testable in isolation. Where a
 * rule does not exist yet, this class throws rather than falling back to
 * something plausible — a wrong rate is an invoice to correct, and correcting
 * invoices across borders is expensive.
 */
final readonly class VatResolver implements VatResolverInterface
{
    public function __construct(
        private RateTable $rates,
        private ViesValidatorInterface $vies,
        private string $homeCountry = 'LU',
    ) {
    }

    public function resolve(
        string $customerCountry,
        CustomerType $type,
        ?string $vatNumber,
        ServiceType $serviceType,
    ): VatResult {
        // 1. Normalise and reject the unknown. get() throws UnknownCountryException.
        $country = $this->rates->get($customerCountry);
        $home = strtoupper(trim($this->homeCountry));

        // 2. Place of supply first — for a physical event it is the venue, and
        //    that overrides everything below, including reverse charge (Art. 53).
        if ($serviceType === ServiceType::PHYSICAL_EVENT) {
            return new VatResult(
                rate: $country->rate($this->categoryFor($serviceType)),
                reverseCharge: false,
                legalBasis: 'Art. 53 MwStSystRL — Leistungsort am Veranstaltungsort; kein Reverse Charge.',
                countryCode: $country->code,
            );
        }

        if ($serviceType !== ServiceType::DIGITAL) {
            throw new UnsupportedServiceTypeException($serviceType);
        }

        // 3. Domestic supply. Reverse charge does not apply between two parties in
        //    the same country, so the customer type makes no difference here.
        if ($country->code === $home) {
            return new VatResult(
                rate: $country->rate(RateCategory::STANDARD),
                reverseCharge: false,
                legalBasis: sprintf(
                    'Inlandsumsatz %s, Art. 2 Abs. 1 Buchst. c MwStSystRL — Regelbesteuerung.',
                    $home,
                ),
                countryCode: $country->code,
            );
        }

        // 4. Outside the EU: no EU VAT is due at all.
        if (!$country->eu) {
            return new VatResult(
                rate: 0.0,
                reverseCharge: false,
                legalBasis: 'Art. 59 MwStSystRL — Leistungsort im Drittland, keine EU-Umsatzsteuer.',
                countryCode: $country->code,
            );
        }

        // 5. EU business with a VAT number VIES has confirmed: reverse charge.
        if ($type === CustomerType::B2B && $vatNumber !== null && trim($vatNumber) !== '') {
            $confirmation = $this->confirmedVatNumber($country->code, $vatNumber);

            if ($confirmation !== null) {
                return new VatResult(
                    rate: 0.0,
                    reverseCharge: true,
                    legalBasis: sprintf(
                        'Art. 196 MwStSystRL — Steuerschuldnerschaft des Leistungsempfängers. '
                        . 'USt-IdNr. %s am %s bestätigt%s.',
                        VatNumberFormat::canonical($country->code, $vatNumber),
                        $confirmation->checkedAt->format('d.m.Y'),
                        $confirmation->consultationNumber !== null
                            ? sprintf(' (Konsultationsnummer %s)', $confirmation->consultationNumber)
                            : '',
                    ),
                    countryCode: $country->code,
                );
            }
        }

        // 6. Everything else: the customer's country rate.
        //    For B2B this is the unconfirmed case, and the reason is spelled out
        //    because it is what an auditor will ask about.
        return new VatResult(
            rate: $country->rate($this->categoryFor($serviceType)),
            reverseCharge: false,
            legalBasis: $type === CustomerType::B2B
                ? 'Art. 58 MwStSystRL — USt-IdNr. nicht bestätigt, Behandlung wie B2C zum Satz des Kundenlands.'
                : 'Art. 58 MwStSystRL — Leistungsort beim Empfänger (elektronisch erbrachte Dienstleistung).',
            countryCode: $country->code,
        );
    }

    /**
     * Which rate band applies to a service type.
     *
     * Constant today. It exists so that the first reduced-rate service is a change
     * to one match arm instead of a change to the rate table's shape.
     */
    private function categoryFor(ServiceType $serviceType): RateCategory
    {
        return match ($serviceType) {
            ServiceType::DIGITAL,
            ServiceType::PHYSICAL_EVENT,
            ServiceType::GOODS => RateCategory::STANDARD,
        };
    }

    /**
     * The VAT number, if and only if VIES positively confirmed it.
     *
     * Both "VIES says invalid" and "we do not know" return null, and they must:
     * granting a reverse charge on an unverified number makes us liable for the
     * tax. The syntax pre-check keeps obvious rubbish from reaching the validator
     * at all.
     */
    private function confirmedVatNumber(string $countryCode, string $vatNumber): ?ViesCheck
    {
        if (!VatNumberFormat::isWellFormed($countryCode, $vatNumber)) {
            return null;
        }

        $check = $this->vies->check($countryCode, $vatNumber);

        return $check?->valid === true ? $check : null;
    }
}
