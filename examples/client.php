<?php
declare(strict_types=1);


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/_functions.php';

$config = require_once __DIR__ . '/_config.php';


$getSms = function () {
};


$x = new class implements \Solodkiy\AlfaBankRuClient\InteractionTrapInterface {

    public function waitForSms(): ?string
    {
        $line = readline('Sms: ');
        return $line ? (string)$line : null;
    }
};


/*
$logger = new SimpleLogger();
$driver = createWebDriver($config['selenium_host'], $config['selenium_port']);
$client = new AlfaBankWebClient($driver, $config['bank_login'], $config['bank_pass'], $x);
$client->setLogger($logger);

$accounts = $client->getAccountsList();
var_dump($accounts);
exit;
*/


//$cookie = readline('Cookie: ');
//$token = readline('csrf-token: ');

$cookie = '';
$token = '';

$headers = [
    "Cookie: " . $cookie,
    'x-csrf-token: ' . $token,
];
$client2 = new \Solodkiy\AlfaBankRuClient\AlfaBankApiClient($headers);
//$r = $client2->downloadAccountHistory($accounts[0]->getNumber());
$r = $client2->downloadAccountHistory('40820810604170002567', '2022-01-22', '2022-02-22');

var_dump($r);
exit;

foreach ($accounts as $account) {
    $logger->info($account->getName() . ' (' . $account->getType() . '): ' . $account->getNumber());
    $json = $client->downloadAccountHistory($account->getNumber());
    $logger->debug($json);
}

