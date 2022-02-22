<?php

declare(strict_types=1);

namespace Solodkiy\AlfaBankRuClient;

interface InteractionTrapInterface
{
    public function waitForSms(): ?string;
}