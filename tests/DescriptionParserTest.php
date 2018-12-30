<?php

namespace Solodkiy\AlfaBankRu;

use Brick\DateTime\LocalDate;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

class DescriptionParserTest extends TestCase
{

    /**
     * @param string $description
     * @param $expectedResult
     * @dataProvider extractHoldProvider
     */
    public function testExtractHold(string $description, $expectedResult)
    {
        $parser = new DescriptionParser();
        $result = $parser->extractHold($description);

        $this->assertEquals($expectedResult, $result);
    }

    public function extractHoldProvider()
    {
        return [
            [
                '808851 RU WHEELY>MOSKVA 17.11.01 17.11.01 1001.02 RUR 111111++++++2222',
                [
                    'code' => '808851',
                    'card' => '111111++++++2222',
                    'company' => 'WHEELY',
                    'amount' => Money::of(1001.02, 'RUB'),
                ]

            ],
            [
                '00000001 US VISIT PATREON COM INFO> 17.11.01 17.11.01 2.00 USD 111111++++++2222',
                [
                    'code' => '00000001',
                    'card' => '111111++++++2222',
                    'company' => 'VISIT PATREON COM INFO',
                    'amount' => Money::of(2, 'USD'),
                ]
            ],
            [
                '26895202 RU Foodfox>MOSKVA G 18.01.25 18.01.25 403.00 RUR 111111++++++2222',
                [
                    'code' => '26895202',
                    'card' => '111111++++++2222',
                    'company' => 'Foodfox',
                    'amount' => Money::of(403, 'RUB'),
                ]
            ],
            [
                '00000001 GB PAYPAL PLAYSTATION>353 17.11.11 17.11.11 1599.00 RUR 111111++++++2222',
                [
                    'code' => '00000001',
                    'card' => '111111++++++2222',
                    'company' => 'PAYPAL PLAYSTATION',
                    'amount' => Money::of(1599, 'RUB'),
                ]
            ],
            [
                'SAS10743 BY SUPERMARKET "GREEN" BA> 18.05.15 18.05.15 .81 BYN 111111++++++2222',
                [
                    'code' => 'SAS10743',
                    'card' => '111111++++++2222',
                    'company' => 'SUPERMARKET "GREEN" BA',
                    'amount' => Money::of(0.81, 'BYN'),
                ]
            ],
            [
                '09170934 RU Yandex.Eda>Moskva 18.06.25 18.06.25 555.00 RUR 111111++++++2222 (Apple Pay-7235)',
                [
                    'code' => '09170934',
                    'card' => '111111++++++2222',
                    'company' => 'Yandex.Eda',
                    'amount' => Money::of(555.00, 'RUB'),
                ]
            ],
            [
                'SWA10162 BY >MINSK 18.07.30 18.07.30 30.00 BYN 111111++++++2222',
                [
                    'code' => 'SWA10162',
                    'card' => '111111++++++2222',
                    'company' => '',
                    'amount' => Money::of(30.00, 'BYN'),
                ]
            ],
            [
                'J198393_ RU LLC MHT>MOSCOW 18.10.24 18.10.24 199.00 RUR 111111++++++2222',
                [
                    'code' => 'J198393',
                    'card' => '111111++++++2222',
                    'company' => 'LLC MHT',
                    'amount' => Money::of(199.00, 'RUB'),
                ]
            ]
        ];
    }

    /**
     * @param string $description
     * @param $expectedResult
     * @dataProvider extractCommittedProvider
     */
    public function testExtractCommitted(string $description, $expectedResult)
    {
        $parser = new DescriptionParser();
        $result = $parser->extractCommitted($description);

        $this->assertEquals($expectedResult, $result);
    }

    public function extractCommittedProvider()
    {
        return [
            [
                '111111++++++2222      808851\643\MOSKVA\WHEELY                        19.01.18 18.01.18      1041.00  RUR MCC4121',
                [
                    'code' => '808851',
                    'card' => '111111++++++2222',
                    'amount' => Money::of(1041, 'RUB'),
                    'description' => '643\MOSKVA\WHEELY',
                    'company' => 'WHEELY',
                    'hold_date' => LocalDate::of(2018, 1, 18),
                ]

            ],
            [
                '111111++++++2222    11231312\RUS\MYTISHCHI\15 \LOGISTIK M             09.01.18 05.01.18      9915.12  RUR MCC5722',
                [
                    'code' => '11231312',
                    'card' => '111111++++++2222',
                    'amount' => Money::of(9915.12, 'RUB'),
                    'description' => 'RUS\MYTISHCHI\15 \LOGISTIK M',
                    'company' => 'LOGISTIK M',
                    'hold_date' => LocalDate::of(2018, 1, 5)
                ]
            ],
            [
                '111111++++++2222    35568FA1\RUS\MOSCOW\KHOROS\ALYE PARUSA            11.07.17 09.07.17      2307.10  RUR MCC5411',
                [
                    'code' => '35568FA1',
                    'card' => '111111++++++2222',
                    'amount' => Money::of(2307.10, 'RUB'),
                    'description' => 'RUS\MOSCOW\KHOROS\ALYE PARUSA',
                    'company' => 'ALYE PARUSA',
                    'hold_date' => LocalDate::of(2017, 7, 9),
                ]
            ],
            [
                '111111++++++2222    SBG44776\BLR\MINSK\ST METRO  PL                   12.10.17 09.10.17          .60  BYN MCC4789',
                [
                    'code' => 'SBG44776',
                    'card' => '111111++++++2222',
                    'amount' => Money::of(0.60, 'BYN'),
                    'description' => 'BLR\MINSK\ST METRO  PL',
                    'company' => 'ST METRO  PL',
                    'hold_date' => LocalDate::of(2017, 10, 9),
                ]
            ],
            [
                '111111++++++2222    26895202\RUS\MOSKVA\26  LE\Yandex Eda             23.05.18 20.05.18       780.00  RUR (Apple Pay-7235) MCC5814',
                [
                    'code' => '26895202',
                    'card' => '111111++++++2222',
                    'description' => 'RUS\MOSKVA\26  LE\Yandex Eda',
                    'hold_date' => LocalDate::of(2018, 5, 20),
                    'company' => 'Yandex Eda',
                    'amount' => Money::of(780.00, 'RUB'),
                ]
            ],
            [
                '111111++++++2222    J198393 \RUS\MOSCOW\LLC MHT                       27.10.18 24.10.18       199.00  RUR MCC5411',
                [
                    'code' => 'J198393',
                    'card' => '111111++++++2222',
                    'description' => 'RUS\MOSCOW\LLC MHT',
                    'hold_date' => LocalDate::of(2018, 10, 24),
                    'company' => 'LLC MHT',
                    'amount' => Money::of(199.00, 'RUB'),
                ]
            ]
        ];
    }
}
