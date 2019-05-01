PHP client for click.alfabank.ru
================================

**AlfaBankClient** служит для подключение к онлайн-банку и скачиванию csv с историей транзакций.
Для работы необходим [smart-selenium](https://hub.docker.com/r/solodkiy/smart-selenium).
```php
$logger = new SimpleLogger();
$driver = createWebDriver($config['selenium_host'], $config['selenium_port']);
$client = new Solodkiy\AlfaBankRuClient\AlfaBankClient($driver, $config['bank_login'], $config['bank_pass']);
$client->setLogger($logger);

$accounts = $client->getAccountsList();
if (count($accounts)) {
    $account = $accounts[0];
    $logger->info($account->getName() . ': ' . $account->getNumber());
    $csv = $client->downloadAccountHistory($account->getNumber());
    $logger->info($csv);
}
```

## Пример
```bash
# Копируем конфиг
cp examples/_config.dist.php examples/_config.php

# Заполняем логин и пароль от альфа-клика
vim examples/_config.php

# Запускаем smart-selenium
docker run -p4444:4444 -d solodkiy/smart-selenium

# Запускаем пример
php examples/client.php
```
