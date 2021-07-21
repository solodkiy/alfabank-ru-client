<?php
declare(strict_types=1);

use Solodkiy\AlfaBankRuClient\AlfaBankClient;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_functions.php';

$config = require_once __DIR__ . '/_config.php';


$getSms = function () {
    $line = readline('Sms: ');
    return $line ? (string)$line : null;
};



$logger = new SimpleLogger();
$driver = createWebDriver($config['selenium_host'], $config['selenium_port']);
$client = new AlfaBankClient($driver, $config['bank_login'], $config['bank_pass'], $getSms);
$client->setLogger($logger);

$accounts = $client->getAccountsList();
foreach ($accounts as $account) {
    $logger->info($account->getName() . ' (' . $account->getType() . '): ' . $account->getNumber());
    $csv = $client->downloadAccountHistory($account->getNumber());
    $logger->debug($csv);
}

