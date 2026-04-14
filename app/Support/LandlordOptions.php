<?php

namespace App\Support;

class LandlordOptions
{
    public static function listingIntents(): array
    {
        return [
            'for_rent' => 'For rent',
            'for_sale' => 'For sale',
            'for_lease' => 'For lease',
        ];
    }

    public static function ondoLgas(): array
    {
        return [
            'Akoko North-East' => 'Akoko North-East',
            'Akoko North-West' => 'Akoko North-West',
            'Akoko South-East' => 'Akoko South-East',
            'Akoko South-West' => 'Akoko South-West',
            'Akure North' => 'Akure North',
            'Akure South' => 'Akure South',
            'Ese Odo' => 'Ese Odo',
            'Idanre' => 'Idanre',
            'Ifedore' => 'Ifedore',
            'Ilaje' => 'Ilaje',
            'Ile Oluji/Okeigbo' => 'Ile Oluji/Okeigbo',
            'Irele' => 'Irele',
            'Odigbo' => 'Odigbo',
            'Okitipupa' => 'Okitipupa',
            'Ondo East' => 'Ondo East',
            'Ondo West' => 'Ondo West',
            'Ose' => 'Ose',
            'Owo' => 'Owo',
        ];
    }

    public static function landlordDocumentTypes(): array
    {
        return [
            'national_id' => 'National ID',
            'drivers_license' => "Driver's license",
            'voters_card' => "Voter's card",
            'international_passport' => 'International passport',
            'utility_bill' => 'Utility bill',
            'business_registration' => 'Business registration',
            'other' => 'Other',
        ];
    }

    public static function propertyTypes(): array
    {
        return [
            'land' => 'Land',
            'self_contain' => 'Self contain',
            'mini_flat' => 'Mini flat',
            'flat' => 'Flat',
            'duplex' => 'Duplex',
            'bungalow' => 'Bungalow',
            'room_and_parlour' => 'Room and parlour',
            'shop' => 'Shop',
            'office' => 'Office',
            'other' => 'Other',
        ];
    }

    public static function propertyDocumentTypes(): array
    {
        return [
            'ownership_proof' => 'Ownership proof',
            'tenancy_agreement' => 'Tenancy agreement',
            'utility_bill' => 'Utility bill',
            'other' => 'Other',
        ];
    }

    public static function landlordDocumentTypeValues(): array
    {
        return array_keys(self::landlordDocumentTypes());
    }

    public static function listingIntentValues(): array
    {
        return array_keys(self::listingIntents());
    }

    public static function propertyTypeValues(): array
    {
        return array_keys(self::propertyTypes());
    }

    public static function propertyDocumentTypeValues(): array
    {
        return array_keys(self::propertyDocumentTypes());
    }

    public static function multiUnitPropertyTypes(): array
    {
        return [
            'self_contain',
            'shop',
            'office',
        ];
    }

    public static function multiUnitPropertyTypeValues(): array
    {
        return self::multiUnitPropertyTypes();
    }

    public static function ondoLgaValues(): array
    {
        return array_keys(self::ondoLgas());
    }

    public static function listingIntentLabel(?string $intent): string
    {
        return self::listingIntents()[$intent ?? 'for_rent'] ?? self::listingIntents()['for_rent'];
    }

    public static function listingIntentAmountLabel(?string $intent): string
    {
        return match ($intent) {
            'for_sale' => 'Sale price',
            'for_lease' => 'Lease amount',
            default => 'Rent amount',
        };
    }

    public static function landSizeUnits(): array
    {
        return [
            'sqm' => 'Square meters (sqm)',
            'plot' => 'Plot',
            'acre' => 'Acre',
            'hectare' => 'Hectare',
        ];
    }

    public static function landSizeUnitValues(): array
    {
        return array_keys(self::landSizeUnits());
    }

    public static function propertyTypeProfile(?string $propertyType): array
    {
        return self::propertyTypeProfiles()[$propertyType ?? ''] ?? [
            'shows_bedrooms' => true,
            'shows_bathrooms' => true,
            'shows_toilets' => true,
            'supports_multi_unit_inventory' => false,
            'default_total_units' => 1,
            'default_bedrooms' => null,
            'default_bathrooms' => null,
            'default_toilets' => null,
            'type_help' => 'Choose the property type first so we can guide the rest of the form more accurately.',
            'room_help' => 'Room counts are optional, but they help explain the layout when they apply.',
            'unit_help' => 'Single-listing property types usually stay at 1 unit unless you are intentionally marketing multiple units together.',
        ];
    }

    protected static function propertyTypeProfiles(): array
    {
        return [
            'land' => [
                'shows_bedrooms' => false,
                'shows_bathrooms' => false,
                'shows_toilets' => false,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Land listings focus on parcel details and size instead of indoor rooms.',
                'room_help' => 'Room counts do not apply to land-only listings.',
                'unit_help' => 'Use 1 unless this listing represents multiple plots or parcels for sale or lease.',
            ],
            'self_contain' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => true,
                'default_total_units' => 1,
                'default_bedrooms' => 1,
                'default_bathrooms' => 1,
                'default_toilets' => 1,
                'type_help' => 'Self contain listings usually follow a one-room layout, so we prefill sensible 1-room defaults.',
                'room_help' => 'Keep the 1-room defaults unless this unit genuinely differs.',
                'unit_help' => 'Self contain listings often come in blocks. Enter the total number of units you want this listing to represent.',
            ],
            'mini_flat' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => 1,
                'default_bathrooms' => 1,
                'default_toilets' => 1,
                'type_help' => 'Mini flats usually start as a one-bedroom layout, but you can adjust the room counts if needed.',
                'room_help' => 'Use room counts to describe the unit clearly for tenants.',
                'unit_help' => 'Mini-flat listings usually describe one unit, so 1 is the sensible starting point.',
            ],
            'room_and_parlour' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => 1,
                'default_bathrooms' => 1,
                'default_toilets' => 1,
                'type_help' => 'Room and parlour listings are usually a one-bedroom style layout, so we start with sensible defaults.',
                'room_help' => 'Adjust the room counts only if this layout genuinely differs from the usual setup.',
                'unit_help' => 'Room-and-parlour listings usually describe one unit, so 1 is the sensible starting point.',
            ],
            'flat' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Flat listings usually need clear room counts so tenants can compare layouts confidently.',
                'room_help' => 'Add bedrooms, bathrooms, and toilets where they help explain the unit.',
                'unit_help' => 'Use 1 unless this listing intentionally represents multiple flats being marketed together.',
            ],
            'duplex' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Duplex listings usually need fuller room counts and a clear description of the layout.',
                'room_help' => 'Use room counts to describe the scale of the duplex accurately.',
                'unit_help' => 'Duplex listings usually describe one home, so 1 is the sensible starting point.',
            ],
            'bungalow' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Bungalow listings often vary in size, so clear room counts help set expectations.',
                'room_help' => 'Use room counts to describe the bungalow layout accurately.',
                'unit_help' => 'Bungalow listings usually describe one home, so 1 is the sensible starting point.',
            ],
            'shop' => [
                'shows_bedrooms' => false,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => true,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Shop listings focus on the commercial space, so bedroom count is hidden because it does not apply.',
                'room_help' => 'Bathroom and toilet counts are optional for commercial spaces.',
                'unit_help' => 'Shop listings often include multiple stalls or bays. Enter the total units this listing starts with.',
            ],
            'office' => [
                'shows_bedrooms' => false,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => true,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Office listings focus on workspace details, so bedroom count is hidden because it does not apply.',
                'room_help' => 'Bathroom and toilet counts are optional for office spaces.',
                'unit_help' => 'Office listings often include multiple suites or workspaces. Enter the total units this listing starts with.',
            ],
            'other' => [
                'shows_bedrooms' => true,
                'shows_bathrooms' => true,
                'shows_toilets' => true,
                'supports_multi_unit_inventory' => false,
                'default_total_units' => 1,
                'default_bedrooms' => null,
                'default_bathrooms' => null,
                'default_toilets' => null,
                'type_help' => 'Choose the closest matching type and use the description field to explain anything unusual.',
                'room_help' => 'Add room counts where they help explain the layout.',
                'unit_help' => 'Use 1 unless this listing intentionally represents multiple units being marketed together.',
            ],
        ];
    }
}
