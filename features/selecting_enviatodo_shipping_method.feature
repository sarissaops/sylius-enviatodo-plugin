@enviatodo_shipping @enviatodo_live
Feature: Selecting Enviatodo shipping method
    In order to receive my order via an Enviatodo carrier
    As a Customer
    I want to choose from live Enviatodo rates at checkout

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "T-Shirt" priced at "$20.00"
        And the store allows Enviatodo shipping method "Enviatodo Estafeta" with code "enviatodo_estafeta"
        And the store allows paying Offline

    @ui
    Scenario: Customer sees live Enviatodo rates at checkout
        Given I added product "T-Shirt" to the cart
        When I complete addressing step with email "john@example.com" and "United States" based billing address
        And I select "Enviatodo Estafeta" shipping method
        Then I should see "Enviatodo Estafeta" shipping method with a live rate
