PHP client for click.alfabank.ru
================================

Либа предоставляет полный набор инструментов для организации импорта ваших финансовых транзакций из интернет-банка "Альфа-Клик" в любую внешнюю систему.

**AlfaBankClient** служит для подключение к онлайн-банку и скачиванию csv с историей транзакций.
Для работы необходим [smart-selenium](https://hub.docker.com/r/solodkiy/smart-selenium).
```php
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
```


**TransactionsComparator** позволяет сравнивать уже импортированные транзакции со скаченной csv для того чтобы получить список обновлений.
```php
$loader = new CsvLoader();
$currentCollection = YourStorage::loadCurrentTransactionsFromDb();
$newCollection = $loader->loadFromFile(__DIR__ .'/../tests/data/movementList_2018-03-07_19:45:18.csv');

$differ = new TransactionsComparator();
$diff = $differ->diff($currentCollection, $newCollection);

YourStorage::insertTransactionsToDb($diff->getNewCommitted());
YourStorage::insertTransactionsToDb($diff->getNewHold());
YourStorage::updateTransactionsInDb($diff->getUpdated());
YourStorage::deleteTransactionsFromDb($diff->getDeletedIds());
```