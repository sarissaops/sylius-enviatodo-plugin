<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Behat\Page\Admin\Order;

interface ShowPageInterface extends \Sylius\Behat\Page\Admin\Order\ShowPageInterface
{
    public function hasEnviatodoTracking(string $tracking): bool;
}
