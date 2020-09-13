<?php
declare(strict_types=1);

namespace Solodkiy\AlfaBankRuClient;

class AlfaBankClientException extends \RuntimeException
{
    public const UNKNOWN_AUTH_ERROR = 1;
    public const ACCOUNT_LOCKED = 2;
    public const INVALID_PASSWORD = 3;

}
