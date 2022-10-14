<?php

declare(strict_types=1);

namespace Solodkiy\AlfaBankRuClient;

use Brick\Money\Money;

class AlfaBankApiClient
{
    private array $tokenHeaders;

    public function __construct(array $tokenHeaders)
    {
        $this->tokenHeaders = $tokenHeaders;
    }

    /**
     * @return AccountData[]
     */
    public function getAccounts(): array
    {
        $result = [];
        $response = $this->makeRequest('https://web.alfabank.ru/newclick-dashboard-ui/proxy/self-transfer-api/transferable-accounts');
        foreach ($response->accounts as $accountData) {
            $dto = new AccountData(
                Money::ofMinor($accountData->amount->value, Utils::fixCurrencyCode($accountData->amount->currency)),
                $accountData->number,
                $accountData->description,
                $this->extractType($accountData->type)
            );
            $result[] = $dto;
        }
        return $result;
    }

    private function extractType(string $type): string
    {
        $map = [
            'EE' => AccountData::ACCOUNT_TYPE_CURRENT,
            'SE' => AccountData::ACCOUNT_TYPE_FAMILY,
            'FY' => AccountData::ACCOUNT_TYPE_ALFA_ACCOUNT,
            'EH' => AccountData::ACCOUNT_TYPE_SALARY,
            'GK' => AccountData::ACCOUNT_TYPE_BROKER,
        ];

        if (!array_key_exists($type, $map)) {
            return AccountData::ACCOUNT_TYPE_UNKNOWN;
        }

        return $map[$type];
    }

    /**
     * @param string $number
     * @param string $from
     * @param string $to
     * @return string
     * @throws \RuntimeException
     */
    public function downloadAccountHistory(string $number, string $from, string $to): array
    {
        $page = 1;
        $pageSize = 100;

        $result = [];
        while (true) {
            $operations = $this->makeGetOperationsRequest($page, $pageSize, $number, $from, $to);
            if (count($operations) === 0) {
                break;
            }
            $result = array_merge($result, $operations);
            $page++;
        }

        // Unique data
        $checkMap = [];
        foreach ($result as $i => $operation) {
            $key = md5(json_encode($operation));
            if (array_key_exists($key, $checkMap)) {
                unset($result[$i]);
            } else {
                $checkMap[$key] = true;
            }
        }
        unset($checkMap);

        return array_values($result);
    }

    public function getTransactionDetails(string $transactionId): \stdClass
    {
        $result = $this->makeRequest('https://web.alfabank.ru/newclick-dashboard-ui/proxy/operations-history-api/operations/' . rawurlencode($transactionId));

        return $result;
    }

    private function makeGetOperationsRequest(int $page, int $pageSize, string $account, string $from, string $to): array
    {
        $request = [
            'size' => $pageSize,
            'page' => $page,
            'filters' => [
                [
                    'values' => [$account],
                    'type' => 'accounts',
                ]
            ],
            'from' => $from,
            'to' => $to,
            'forced' => true,
        ];

        $pageData = $this->makeRequest(
            'https://web.alfabank.ru/newclick-dashboard-ui/proxy/operations-history-api/operations',
            $request
        );

        $operations = $pageData->operations;
        if (!is_array($operations)) {
            throw new \RuntimeException('Incorrect operations list');
        }
        return $operations;
    }

    private function makeRequest(string $url, ?array $postData = null)
    {
        $headers = array_merge(
            $this->tokenHeaders,
            [
                'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.80 Safari/537.36',
                'Content-Type: application/json;charset=UTF-8',
                'Accept: application/json, text/plain, */*',
                'Origin: https://web.alfabank.ru',
                'Referer: https://web.alfabank.ru/history/',
                'Accept-Language: en-US,en;q=0.9,ru;q=0.8',
            ]
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //curl_setopt($ch, CURLOPT_VERBOSE, 1);
        //curl_setopt($ch, CURLOPT_STDERR, $verbose = fopen('php://temp', 'rw+'));
        $result = curl_exec($ch);
        if ($result === '') {
            throw new \RuntimeException('Empty response');
        }
        $pageData = json_decode($result);
        if (is_null($pageData)) {
            throw new \RuntimeException('Incorrect json');
        }

        return $pageData;
    }


}