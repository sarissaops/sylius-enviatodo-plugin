@enviatodo_config
Feature: Viewing Enviatodo shipment data in administration
    In order to follow parcel fulfilment
    As an Administrator
    I want to see Enviatodo tracking data on the order page

    Background:
        Given I am logged in as an administrator

    @ui
    Scenario: Administrator sees tracking data on the order page
        Given the order "#0001" has an Enviatodo shipment with tracking "TRK1"
        When I view the summary of the order "#0001"
        Then I should see Enviatodo tracking "TRK1" with handover "in_transit"
