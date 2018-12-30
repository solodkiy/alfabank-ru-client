<?php
declare(strict_types=1);

use Solodkiy\AlfaBankRu\AlfaBankClient;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_functions.php';

$config = require_once __DIR__ . '/_config.php';

$logger = new SimpleLogger();
$driver = createWebDriver($config['selenium_host'], $config['selenium_port']);
$client = new AlfaBankClient($driver, $config['bank_login'], $config['bank_pass']);
$client->setLogger($logger);

$accounts = $client->getAccountsList();
if (count($accounts)) {
    $account = $accounts[0];
    $logger->info($account->getName() . ': ' . $account->getNumber());
    $csv = $client->downloadAccountHistory($account->getNumber());
    $logger->info($csv);
}
