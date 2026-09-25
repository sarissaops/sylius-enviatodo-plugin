@enviatodo_shipping
Feature: Hiding Enviatodo shipping method for unquotable destinations
    In order to check out without errors
    As a Customer
    I do not want Enviatodo methods listed when no live rate exists

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "T-Shirt" priced at "$20.00"
        And the store allows Enviatodo shipping method "Enviatodo Estafeta" with code "enviatodo_estafeta"
        And the store allows paying Offline

    @ui @javascript
    Scenario: Customer does not see Enviatodo method for a US address
        When I add product "T-Shirt" to the cart
        And I check items in my cart
        Then there should be one item in my cart
        When I complete addressing step with email "john@example.com" and "United States" based billing address
        Then I should not see "Enviatodo Estafeta" shipping method
