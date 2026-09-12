<?php

declare(strict_types=1);

namespace Helmetsan\Core\Geo;

/**
 * ComplianceService
 *
 * Evaluates motorcycle helmet homologation certifications, road legality by market,
 * and customs / import duty estimates for cross-border gear procurement.
 */
class ComplianceService
{
    /**
     * Country-specific mandatory road helmet standards.
     */
    private const COUNTRY_STANDARDS = [
        'US' => [
            'standard' => 'FMVSS 218 (DOT)',
            'match_keywords' => ['DOT', 'FMVSS 218', 'FMVSS218'],
            'name' => 'United States',
            'regulatory_body' => 'NHTSA',
            'legal_summary' => 'Mandatory for street riding in all states with helmet laws.',
            'non_compliance_note' => 'Under US Federal Motor Vehicle Safety Standard No. 218, helmets without a valid DOT certification are not street-legal on public roads where helmet laws apply.',
        ],
        'CA' => [
            'standard' => 'CMVSS 218 / DOT / ECE / Snell',
            'match_keywords' => ['DOT', 'ECE', 'SNELL', 'CAN3-D230'],
            'name' => 'Canada',
            'regulatory_body' => 'Transport Canada',
            'legal_summary' => 'Approved across all Canadian provinces accepting DOT, ECE 22.05/22.06, or Snell.',
            'non_compliance_note' => 'Canadian provincial highway traffic acts require DOT, Snell, or ECE 22.05/22.06 certification for street legality.',
        ],
        'GB' => [
            'standard' => 'UNECE 22.05 / 22.06 or BS 6658',
            'match_keywords' => ['ECE 22.05', 'ECE 22.06', 'ECE22.05', 'ECE22.06', 'ECE', 'BS 6658'],
            'name' => 'United Kingdom',
            'regulatory_body' => 'DVSA / Department for Transport',
            'legal_summary' => 'Fully street-legal across the UK under Road Traffic Act standards.',
            'non_compliance_note' => 'Under UK law (The Motor Cycles [Protective Helmets] Regulations), road helmets must meet UNECE Regulation 22.05, 22.06 or British Standard BS 6658. DOT-only helmets are not street-legal.',
        ],
        'DE' => [
            'standard' => 'ECE 22.05 / ECE 22.06',
            'match_keywords' => ['ECE 22.05', 'ECE 22.06', 'ECE22.05', 'ECE22.06', 'ECE'],
            'name' => 'Germany & European Union',
            'regulatory_body' => 'UNECE / KBA',
            'legal_summary' => 'Fully homologated for EU and EEA public roads.',
            'non_compliance_note' => 'Under European Council Directive and national StVO laws, helmets must be certified to UN/ECE Regulation No. 22 (05 or 06). Non-ECE lids risk fines and impoundment.',
        ],
        'FR' => [
            'standard' => 'ECE 22.05 / ECE 22.06 + Retro-reflective elements',
            'match_keywords' => ['ECE 22.05', 'ECE 22.06', 'ECE22.05', 'ECE22.06', 'ECE'],
            'name' => 'France',
            'regulatory_body' => 'Sécurité Routière',
            'legal_summary' => 'ECE homologated; French law additionally mandates 4 reflective stickers (front, rear, sides).',
            'non_compliance_note' => 'Requires ECE 22.05/22.06 certification. France strictly requires 4 reflective stickers affixed to the shell to avoid a 3-point driving license penalty and fine.',
        ],
        'IT' => [
            'standard' => 'ECE 22.05 / ECE 22.06',
            'match_keywords' => ['ECE 22.05', 'ECE 22.06', 'ECE22.05', 'ECE22.06', 'ECE'],
            'name' => 'Italy',
            'regulatory_body' => 'Ministero dei Trasporti',
            'legal_summary' => 'ECE certified for public highway use under Codice della Strada.',
            'non_compliance_note' => 'Art. 171 Codice della Strada requires UN/ECE 22.05 or 22.06 compliance. Non-compliant helmets subject rider to vehicle seizure for up to 60 days.',
        ],
        'ES' => [
            'standard' => 'ECE 22.05 / ECE 22.06',
            'match_keywords' => ['ECE 22.05', 'ECE 22.06', 'ECE22.05', 'ECE22.06', 'ECE'],
            'name' => 'Spain',
            'regulatory_body' => 'DGT (Dirección General de Tráfico)',
            'legal_summary' => 'Homologated for Spanish roads under DGT regulations.',
            'non_compliance_note' => 'Spanish DGT requires ECE 22.05 or 22.06 homologation. Unapproved helmets incur a €200 fine and 4 penalty points.',
        ],
        'IN' => [
            'standard' => 'IS 4151:2015 (ISI / BIS Mark)',
            'match_keywords' => ['ISI', 'BIS', 'IS 4151', 'IS4151'],
            'name' => 'India',
            'regulatory_body' => 'Bureau of Indian Standards (BIS) & MoRTH',
            'legal_summary' => 'ISI certified and compliant with Section 129 of the Motor Vehicles Act.',
            'non_compliance_note' => 'Under Section 129 of the Indian Motor Vehicles Act and MoRTH quality control orders, two-wheeler riders must wear BIS-certified (IS 4151) helmets bearing the ISI mark. Imported ECE/DOT helmets without ISI certification are technically non-compliant for public roads.',
        ],
        'JP' => [
            'standard' => 'JIS T 8133 / PSC',
            'match_keywords' => ['JIS', 'PSC', 'JIS T 8133', 'MFJ'],
            'name' => 'Japan',
            'regulatory_body' => 'METI / Japan Helmet Association',
            'legal_summary' => 'Compliant with Japanese Consumer Product Safety Act (PSC) and JIS standards.',
            'non_compliance_note' => 'Riding on Japanese public roads requires helmets conforming to the Road Traffic Act and PSC safety standard marks.',
        ],
        'AU' => [
            'standard' => 'AS/NZS 1698:2006 or UNECE 22.05 / 22.06',
            'match_keywords' => ['AS/NZS 1698', 'AS 1698', 'AS/NZS', 'ECE 22.05', 'ECE 22.06', 'ECE22.05', 'ECE22.06', 'ECE'],
            'name' => 'Australia & New Zealand',
            'regulatory_body' => 'ADR / Standards Australia',
            'legal_summary' => 'Approved under Australian Road Rules (AS/NZS 1698 or UNECE 22.05/22.06).',
            'non_compliance_note' => 'Australian state road laws mandate either an AS/NZS 1698 sticker or UNECE 22.05 / 22.06 compliance label.',
        ],
        'BR' => [
            'standard' => 'INMETRO NBR 7471',
            'match_keywords' => ['INMETRO', 'NBR 7471', 'NBR7471'],
            'name' => 'Brazil',
            'regulatory_body' => 'CONTRAN / INMETRO',
            'legal_summary' => 'Certified under INMETRO NBR 7471 with mandatory reflective markings.',
            'non_compliance_note' => 'Brazilian CONTRAN Resolution 453 mandates an INMETRO seal and retro-reflective decals on all sides. Riding without is a serious traffic violation.',
        ],
    ];

    /**
     * Evaluate road legality status for a helmet's certifications in a specific market.
     *
     * @param array<string> $certifications Array of certification strings (e.g. ['DOT', 'ECE 22.06'])
     * @param string        $countryCode    ISO 3166-1 alpha-2 country code (e.g. 'US', 'DE', 'IN')
     * @return array<string, mixed>
     */
    public function getRoadLegalityStatus(array $certifications, string $countryCode = 'US'): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $marketRule = self::COUNTRY_STANDARDS[$countryCode] ?? self::COUNTRY_STANDARDS['US'];

        // Normalize certifications list
        $normalizedCerts = array_map(static fn($c) => trim((string)$c), $certifications);
        $normalizedCertsUpper = array_map('strtoupper', $normalizedCerts);

        // Check if track/racing only (e.g. FIM without DOT/ECE or dedicated track badge)
        $isFim = false;
        foreach ($normalizedCertsUpper as $cert) {
            if (str_contains($cert, 'FIM')) {
                $isFim = true;
                break;
            }
        }

        // Check market-specific compliance
        $isCompliantForMarket = false;
        $matchedKeyword = null;

        foreach ($marketRule['match_keywords'] as $keyword) {
            $keywordUpper = strtoupper($keyword);
            foreach ($normalizedCertsUpper as $cert) {
                if (str_contains($cert, $keywordUpper)) {
                    $isCompliantForMarket = true;
                    $matchedKeyword = $keyword;
                    break 2;
                }
            }
        }

        // EU fallback: If in an EU country not explicitly listed, apply EU/ECE rule
        if (! $isCompliantForMarket && $this->isEuCountry($countryCode)) {
            $euRule = self::COUNTRY_STANDARDS['DE'];
            foreach ($euRule['match_keywords'] as $keyword) {
                $keywordUpper = strtoupper($keyword);
                foreach ($normalizedCertsUpper as $cert) {
                    if (str_contains($cert, $keywordUpper)) {
                        $isCompliantForMarket = true;
                        $matchedKeyword = $keyword;
                        $marketRule = $euRule;
                        break 2;
                    }
                }
            }
        }

        if ($isCompliantForMarket) {
            return [
                'status' => 'road_legal',
                'is_legal' => true,
                'title' => sprintf('Street-Legal in %s (%s)', $marketRule['name'], $matchedKeyword ?? $marketRule['standard']),
                'subtitle' => 'Meets regional safety homologation requirements',
                'regional_note' => $marketRule['legal_summary'],
                'certifications' => $normalizedCerts,
                'country' => $countryCode,
            ];
        }

        if ($isFim) {
            return [
                'status' => 'track_only',
                'is_legal' => false,
                'title' => 'FIM Competition / Track Certified',
                'subtitle' => 'Specialized circuit homologation',
                'regional_note' => 'Certified for professional track competition under FIM FRHPhe regulations. Check local street homologation before highway use.',
                'certifications' => $normalizedCerts,
                'country' => $countryCode,
            ];
        }

        return [
            'status' => 'warning',
            'is_legal' => false,
            'title' => $countryCode === 'IN' 
                ? 'Import Advisory: Missing ISI / BIS Mark' 
                : sprintf('Not %s Certified', $marketRule['standard']),
            'subtitle' => sprintf('May not satisfy public highway laws in %s', $marketRule['name']),
            'regional_note' => $marketRule['non_compliance_note'],
            'certifications' => $normalizedCerts,
            'country' => $countryCode,
        ];
    }

    /**
     * Compute customs import duties, taxes, and de minimis threshold exemptions.
     *
     * @param float  $priceAmount  Current item price in given currency
     * @param string $currency     Price currency (e.g. 'USD', 'EUR', 'GBP', 'INR')
     * @param string $countryCode  Destination country ISO code
     * @return array<string, mixed>
     */
    public function getImportDutyEstimate(float $priceAmount, string $currency, string $countryCode = 'US'): array
    {
        $countryCode = strtoupper(trim($countryCode));

        // Regional tariff rules & thresholds
        $rules = [
            'US' => [
                'currency' => 'USD',
                'de_minimis_threshold' => 800.0, // Section 321 de minimis
                'duty_rate_pct' => 0.0,          // 0% under $800, standard ~0-3% for safety gear
                'vat_rate_pct' => 0.0,           // No federal sales tax / VAT
                'hs_code' => '6506.10',
                'summary' => 'Imports up to $800 USD qualify for Section 321 de minimis duty-free entry into the United States.',
            ],
            'GB' => [
                'currency' => 'GBP',
                'de_minimis_threshold' => 135.0, // Below £135 VAT collected at point of sale, duty 0%
                'duty_rate_pct' => 2.5,
                'vat_rate_pct' => 20.0,
                'hs_code' => '6506.10',
                'summary' => 'Under £135: VAT collected at checkout, 0% customs duty. Over £135: 20% VAT + approx. 2.5% customs duty applied upon entry.',
            ],
            'DE' => [
                'currency' => 'EUR',
                'de_minimis_threshold' => 150.0, // EU threshold: under €150 no customs duty (only VAT)
                'duty_rate_pct' => 2.7,
                'vat_rate_pct' => 19.0,
                'hs_code' => '6506.10.10',
                'summary' => 'Under €150: Exempt from EU customs duty (19% German VAT applies). Over €150: 2.7% customs duty + 19% VAT.',
            ],
            'FR' => [
                'currency' => 'EUR',
                'de_minimis_threshold' => 150.0,
                'duty_rate_pct' => 2.7,
                'vat_rate_pct' => 20.0,
                'hs_code' => '6506.10.10',
                'summary' => 'Under €150: Customs duty exempt (20% French TVA applies). Over €150: 2.7% EU customs duty + 20% TVA.',
            ],
            'IT' => [
                'currency' => 'EUR',
                'de_minimis_threshold' => 150.0,
                'duty_rate_pct' => 2.7,
                'vat_rate_pct' => 22.0,
                'hs_code' => '6506.10.10',
                'summary' => 'Under €150: Customs duty exempt (22% Italian IVA applies). Over €150: 2.7% customs duty + 22% IVA.',
            ],
            'ES' => [
                'currency' => 'EUR',
                'de_minimis_threshold' => 150.0,
                'duty_rate_pct' => 2.7,
                'vat_rate_pct' => 21.0,
                'hs_code' => '6506.10.10',
                'summary' => 'Under €150: Customs duty exempt (21% Spanish IVA applies). Over €150: 2.7% customs duty + 21% IVA.',
            ],
            'IN' => [
                'currency' => 'INR',
                'de_minimis_threshold' => 0.0, // Zero de minimis for commercial imports
                'duty_rate_pct' => 10.0,      // Basic Customs Duty on motorcycle headgear
                'vat_rate_pct' => 18.0,       // IGST rate for imported safety helmets
                'hs_code' => '6506.10.00',
                'summary' => 'Imported motorcycle helmets are subject to approx. 10% Basic Customs Duty (BCD) + 18% IGST + Social Welfare Surcharge (SWS).',
            ],
            'CA' => [
                'currency' => 'CAD',
                'de_minimis_threshold' => 20.0, // $20 CAD postal de minimis ($40 for courier from US/MX)
                'duty_rate_pct' => 0.0,         // Motorcycle safety helmets generally duty-free under tariff 6506.10
                'vat_rate_pct' => 5.0,          // Federal GST (provincial HST/PST may apply)
                'hs_code' => '6506.10',
                'summary' => 'Motorcycle safety helmets enter Canada duty-free (0% duty), subject to 5% GST and applicable provincial sales taxes.',
            ],
            'AU' => [
                'currency' => 'AUD',
                'de_minimis_threshold' => 1000.0, // $1,000 AUD threshold
                'duty_rate_pct' => 0.0,
                'vat_rate_pct' => 10.0,           // 10% GST
                'hs_code' => '6506.10',
                'summary' => 'Imports under $1,000 AUD are duty-free (10% GST applies at purchase). Orders over $1,000 require formal customs clearance.',
            ],
            'JP' => [
                'currency' => 'JPY',
                'de_minimis_threshold' => 10000.0, // 10,000 JPY threshold
                'duty_rate_pct' => 0.0,           // Safety helmets duty-free
                'vat_rate_pct' => 10.0,           // 10% Consumption Tax
                'hs_code' => '6506.10',
                'summary' => 'Safety headgear enters Japan duty-free (0% tariff), subject to 10% Japanese consumption tax.',
            ],
        ];

        // Default fallback rule
        $rule = $rules[$countryCode] ?? [
            'currency' => 'USD',
            'de_minimis_threshold' => 100.0,
            'duty_rate_pct' => 5.0,
            'vat_rate_pct' => 15.0,
            'hs_code' => '6506.10',
            'summary' => 'International shipment: local customs duties, import tariffs, and national VAT may apply upon customs clearance.',
        ];

        $isExempt = ($rule['de_minimis_threshold'] > 0.0 && $priceAmount <= $rule['de_minimis_threshold']);
        $dutyRate = $isExempt ? 0.0 : $rule['duty_rate_pct'];
        $vatRate = $rule['vat_rate_pct'];

        $estimatedDuty = round($priceAmount * ($dutyRate / 100.0), 2);
        // VAT usually applies to price + duty
        $taxableBase = $priceAmount + $estimatedDuty;
        $estimatedVat = round($taxableBase * ($vatRate / 100.0), 2);
        $estimatedLandedCost = round($priceAmount + $estimatedDuty + $estimatedVat, 2);

        return [
            'destination_country' => $countryCode,
            'price_amount' => $priceAmount,
            'currency' => $currency,
            'hs_code' => $rule['hs_code'],
            'is_duty_exempt' => $isExempt,
            'de_minimis_threshold' => $rule['de_minimis_threshold'],
            'duty_rate_pct' => $rule['duty_rate_pct'],
            'vat_rate_pct' => $rule['vat_rate_pct'],
            'estimated_duty' => $estimatedDuty,
            'estimated_vat' => $estimatedVat,
            'estimated_landed_total' => $estimatedLandedCost,
            'summary' => $rule['summary'],
        ];
    }

    /**
     * Retrieve regulatory and market standards requirements for a given country.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 code
     * @return array<string, mixed>
     */
    public function getMarketRequirements(string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $rule = self::COUNTRY_STANDARDS[$countryCode] ?? null;

        if ($rule === null && $this->isEuCountry($countryCode)) {
            $rule = self::COUNTRY_STANDARDS['DE'];
        }

        if ($rule === null) {
            $rule = [
                'standard' => 'UN/ECE 22.05 or DOT FMVSS 218',
                'match_keywords' => ['ECE 22.05', 'ECE 22.06', 'DOT'],
                'name' => 'International Market',
                'regulatory_body' => 'National Transport Authority',
                'legal_summary' => 'Compliant with international UNECE or US DOT standards.',
                'non_compliance_note' => 'Please consult local traffic regulations for recognized safety certifications.',
            ];
        }

        // Get de minimis if configured in getImportDutyEstimate rules
        $dutyRule = $this->getImportDutyEstimate(100.0, 'USD', $countryCode);

        return [
            'country' => $countryCode,
            'name' => $rule['name'],
            'standard' => $rule['standard'],
            'accepted_standards' => $rule['match_keywords'],
            'regulatory_body' => $rule['regulatory_body'],
            'legal_summary' => $rule['legal_summary'],
            'de_minimis_threshold' => $dutyRule['de_minimis_threshold'],
        ];
    }

    /**
     * Check if a country code belongs to the EU.
     */
    private function isEuCountry(string $country): bool
    {
        $euCountries = [
            'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PL', 'SE', 'DK', 'FI',
            'PT', 'GR', 'CZ', 'RO', 'HU', 'IE', 'SK', 'BG', 'HR', 'LT', 'SI',
            'LV', 'EE', 'CY', 'LU', 'MT'
        ];
        return in_array($country, $euCountries, true);
    }
}
