<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Behat\Page\Admin\Order;

use Sylius\Behat\Page\Admin\Order\ShowPage as BaseShowPage;

class ShowPage extends BaseShowPage implements ShowPageInterface
{
    public function hasEnviatodoTracking(string $tracking): bool
    {
        return str_contains($this->getDocument()->getContent(), $tracking);
    }
}
