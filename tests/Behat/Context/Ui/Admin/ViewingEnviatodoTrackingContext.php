<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Tests\SarissaOps\SyliusEnviatodoPlugin\Behat\Page\Admin\Order\ShowPageInterface;
use Webmozart\Assert\Assert;

final class ViewingEnviatodoTrackingContext implements Context
{
    public function __construct(private ShowPageInterface $showPage)
    {
    }

    /**
     * @Then I should see Enviatodo tracking :tracking
     */
    public function seeEnviatodoTracking(string $tracking): void
    {
        Assert::true(
            $this->showPage->hasEnviatodoTracking($tracking),
            sprintf('Enviatodo tracking "%s" was not visible on the order page.', $tracking),
        );
    }
}
