<?php

namespace Solodkiy\AlfaBankRu;

use PHPUnit\Framework\TestCase;

class TransactionsComparatorTest extends TestCase
{
    /**
     * @param string $currentPath
     * @param string $newPath
     * @param array $expectedStat
     * @dataProvider createDiffProvider
     */
    public function testCreateDiff(string $currentPath, string $newPath, array $expectedStat)
    {
        $loader = new CsvLoader();
        $currentCollection = $loader->loadFromFile($currentPath);
        $newCollection = $loader->loadFromFile($newPath);


        $differ = new TransactionsComparator();
        $diff = $differ->diff($currentCollection, $newCollection);

        $this->assertEquals($expectedStat, $diff->stat());
    }

    public function createDiffProvider()
    {
        return [
            [
                __DIR__ . '/data/movementList_2018-02-28_18:15:23.csv',
                __DIR__ . '/data/movementList_2018-02-28_18:15:23.csv',
                [
                    'new_hold' => 0,
                    'new_committed' => 0,
                    'updated' => 0,
                    'deleted' => 0,
                ]
            ],
            [
                __DIR__ . '/data/movementList_2018-02-28_18:15:23.csv',
                __DIR__ . '/data/movementList_2018-03-07_19:45:18.csv',
                [
                    'new_hold' => 5,
                    'new_committed' => 19,
                    'updated' => 6,
                    'deleted' => 0,
                ]
            ],
            [
                __DIR__ . '/data/movementList_2018-02-28_18:15:23.csv',
                __DIR__ . '/data/movementList_2018-02-28_20:00:17.csv',
                [
                    'new_hold' => 0,
                    'new_committed' => 1,
                    'updated' => 0,
                    'deleted' => 0,
                ]
            ],
            [
                __DIR__ . '/data/full/first.csv',
                __DIR__ . '/data/full/second.csv',
                [
                    'new_hold' => 0,
                    'new_committed' => 0,
                    'updated' => 1,
                    'deleted' => 0,
                ]
            ],
            [
                __DIR__ . '/data/empty_csv/movementList_2018-10-20_22:30:36.csv',
                __DIR__ . '/data/empty_csv/movementList_2018-10-21_21:45:35.csv',
                [
                    'new_hold' => 0,
                    'new_committed' => 0,
                    'updated' => 0,
                    'deleted' => 0,
                ]
            ],
        ];
    }
}
