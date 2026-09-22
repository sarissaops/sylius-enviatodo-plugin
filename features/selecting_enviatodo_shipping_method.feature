@enviatodo_shipping
Feature: Selecting Enviatodo shipping method
    In order to receive my order via an Enviatodo carrier
    As a Customer
    I want to choose from live Enviatodo rates at checkout

    Background:
        Given the store operates on a single channel in "Mexico"
        And the store has a product "T-Shirt"
        And the store allows shipping with "Enviatodo Estafeta"
        And I am a logged in customer

    @ui
    Scenario: Customer sees live Enviatodo rates at checkout
        Given I have product "T-Shirt" in the cart
        When I proceed to the checkout
        And I complete the addressing step with "64000" postcode
        Then I should see "Enviatodo Estafeta" shipping method with a live rate
        When I select the "Enviatodo Estafeta" shipping method
        And I complete the checkout
        Then the order shipment should hold an Enviatodo quote uuid
