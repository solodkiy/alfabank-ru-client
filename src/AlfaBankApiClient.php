<?php

declare(strict_types=1);

namespace Solodkiy\AlfaBankRuClient;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeOutException;
use Solodkiy\SmartSeleniumDriver\Exceptions\SmartSeleniumCommandError;

class AlfaBankApiClient
{
    private array $tokenHeaders;

    public function __construct(array $tokenHeaders)
    {
        $this->tokenHeaders =$tokenHeaders;
    }

    /**
     * @param $number
     * @return string
     * @throws NoSuchElementException
     * @throws TimeOutException
     * @throws SmartSeleniumCommandError
     * @throws AlfaBankClientException
     */
    public function downloadAccountHistory(string $number, $from, $to): string
    {
        $page = 1;
        $pageSize = 10;

        $result = [];
        while (true) {
            $operations = $this->makeRequest($page, $pageSize, $number, $from, $to);
            if (count($operations) === 0) {
                break;
            }
            $result = array_merge($result, $operations);
            $page++;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function makeRequest(int $page, int $pageSize, string $account, string $from, string $to): array
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
        ];

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
        curl_setopt($ch, CURLOPT_URL, 'https://web.alfabank.ru/newclick-operations-history-ui/proxy/operations-history-api/operations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //curl_setopt($ch, CURLOPT_VERBOSE, 1);
        //curl_setopt($ch, CURLOPT_STDERR, $verbose = fopen('php://temp', 'rw+'));
        $result = curl_exec($ch);
        if ($result === '') {
            throw new \RuntimeException('Empty response');
        }
        $pageData = json_decode($result, true);
        if (is_null($pageData)) {
            throw new \RuntimeException('Incorrect json');
        }
        $operations = $pageData['operations'];
        if (!is_array($operations)) {
            throw new \RuntimeException('Incorrect operations list');
        }
        return $operations;
    }

}