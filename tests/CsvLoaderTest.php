<?php


namespace Solodkiy\AlfaBankRu;

use function Functional\map;
use PHPUnit\Framework\TestCase;
use Solodkiy\AlfaBankRu\Model\Transaction;
use Solodkiy\AlfaBankRu\Model\TransactionsCollection;

class CsvLoaderTest extends TestCase
{
    public function testLoadFromFile()
    {
        $fileName = __DIR__ . '/data/small_file.csv';
        $loader = new CsvLoader();
        $result = $loader->loadFromFile(fopen($fileName, 'r'));

        $data = $this->collectionToArray($result);

        $this->assertEquals($this->expectedResult(), $data);
    }

    public function testLoadFromString()
    {
        $content = file_get_contents(__DIR__ . '/data/small_file.csv');
        $loader = new CsvLoader();
        $result = $loader->loadFromString($content);

        $data = $this->collectionToArray($result);

        $this->assertEquals($this->expectedResult(), $data);
    }

    private function expectedResult()
    {
        return [
            [
                'date' => '2018-02-28',
                'account' => '40820000000011112222',
                'amount' => 'RUB 1.00',
                'type' => 'out',
                'description' => '23263612 RU PAYULLC vscale.io>g. Sa 18.02.28 18.02.28 1.00 RUR 111111++++++2222',
            ],
            [
                'date' => '2018-02-28',
                'account' => '40820000000011112222',
                'amount' => 'RUB 1480.00',
                'type' => 'out',
                'description' => '111111++++++2222     J134850\\RUS\\MOSCOW\\CVETNO\\RESTAURANT CE          27.02.18 24.02.18      1480.00  RUR MCC5814',
            ],
            [
                'date' => '2018-02-28',
                'account' => '40820000000011112222',
                'amount' => 'RUB 489.00',
                'type' => 'out',
                'description' => '111111++++++2222    26895202\\RUS\\MOSKVA\\26  LE\\Yandex Eda             27.02.18 24.02.18       489.00  RUR MCC5814',
            ],
        ];
    }

    public function testCents()
    {
        $content = file_get_contents(__DIR__ . '/data/cents.csv');
        $loader = new CsvLoader();
        $result = $loader->loadFromString($content);

        $data = $this->collectionToArray($result);

        $expectedResult = [
            [
                'date' => '2019-03-31',
                'account' => '40820000000011112222',
                'amount' => 'USD 0.38',
                'type' => 'in',
                'description' => 'Выпл.% на мин.остаток за м-ц по ставке 0.1% годовых (S1=7000.00 USD,S3=0.00 USD,S6=0.00 USD,S12=0.00 USD)',
            ],
            [
                'date' => '2019-03-11',
                'account' => '40820000000011112222',
                'amount' => 'USD 7000.00',
                'type' => 'in',
                'description' => 'Внутрибанковский перевод между счетами с конвертацией, XXX.',
            ],
        ];
        $this->assertEquals($expectedResult, $data);
    }

    private function collectionToArray(TransactionsCollection $collection) : array
    {
        return map($collection, function (Transaction $transaction) {
            return [
                'date' => (string)$transaction->getDate(),
                'account' => $transaction->getAccount(),
                'amount' => (string)$transaction->getAmount(),
                'type' => (string)$transaction->getType(),
                'description' => $transaction->getDescription(),
            ];
        });
    }
}
