<?php
declare(strict_types=1);


use Solodkiy\AlfaBankRuClient\AlfaBankApiClient;
use Solodkiy\AlfaBankRuClient\InteractionTrapInterface;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_functions.php';

$config = require_once __DIR__ . '/_config.php';
$logger = new SimpleLogger();

/*
$smsTrap = new class implements InteractionTrapInterface {

    public function waitForSms(): ?string
    {
        $line = readline('Sms: ');
        return $line ? (string)$line : null;
    }
};

$driver = createWebDriver($config['selenium_host'], $config['selenium_port']);
$client = new AlfaBankWebClient($driver, $config['bank_login'], $config['bank_pass'], $smsTrap);
$client->setLogger($logger);

$accounts = $client->getAccountsList();
var_dump($accounts);
exit;
*/


$headers = [
    "Cookie: " . $config['cookie'],
    'x-csrf-token: ' . $config['token'],
];
$client2 = new AlfaBankApiClient($headers);

$accounts = $client2->getAccounts();

foreach ($accounts as $account) {
    $logger->info($account->getName() . ' (' . $account->getType() . '): ' . $account->getNumber());
    $json = $client2->downloadAccountHistory($account->getNumber(), '2022-10-01', '2022-10-15');
    $logger->debug(json_encode($json));
}
