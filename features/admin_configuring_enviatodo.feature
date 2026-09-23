@enviatodo_config
Feature: Viewing Enviatodo shipment data in administration
    In order to follow parcel fulfilment
    As an Administrator
    I want to see Enviatodo tracking data on the order page

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "T-Shirt" priced at "$20.00"
        And the store ships everywhere for Free
        And the store allows paying Offline
        And I am logged in as an administrator

    @ui
    Scenario: Administrator sees tracking data on the order page
        Given the guest customer placed order with "T-Shirt" product for "buyer@example.com" and "United States" based billing address with "Free" shipping method and "Offline" payment
        And the shipment of the latest order has Enviatodo tracking "TRK1"
        When I view the summary of the last order
        Then I should see Enviatodo tracking "TRK1"
