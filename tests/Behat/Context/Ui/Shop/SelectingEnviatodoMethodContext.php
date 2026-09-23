<?php

declare(strict_types=1);

namespace Tests\SarissaOps\SyliusEnviatodoPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Sylius\Behat\Page\Shop\Checkout\SelectShippingPageInterface;
use Webmozart\Assert\Assert;

final class SelectingEnviatodoMethodContext implements Context
{
    public function __construct(private SelectShippingPageInterface $selectShippingPage)
    {
    }

    /**
     * @Then I should see :methodName shipping method with a live rate
     */
    public function seeMethodWithLiveRate(string $methodName): void
    {
        Assert::true(
            \in_array($methodName, $this->selectShippingPage->getShippingMethods(), true),
            sprintf('Shipping method "%s" with a live rate was not listed.', $methodName),
        );
    }
}
