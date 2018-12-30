<?php
declare(strict_types=1);

use Solodkiy\AlfaBankRu\CsvLoader;
use Solodkiy\AlfaBankRu\TransactionsComparator;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_functions.php';

$config = require_once __DIR__ . '/_config.php';


$loader = new CsvLoader();
$currentCollection = $loader->loadFromFile(__DIR__ . '/../tests/data/movementList_2018-02-28_20:00:17.csv');
$newCollection = $loader->loadFromFile(__DIR__ .'/../tests/data/movementList_2018-03-07_19:45:18.csv');

$differ = new TransactionsComparator();
$diff = $differ->diff($currentCollection, $newCollection);

var_dump($diff->stat());
var_dump($diff->getUpdated());

