<?php

namespace App\Support;

use App\Models\MoveInConditionReport;
use App\Models\Occupancy;
use Illuminate\Support\Facades\DB;

class MoveInConditionReportService
{
    public const RATINGS = ['good', 'fair', 'damaged', 'not_applicable'];

    /** @return array<string, array<string, string>> */
    public static function checklist(): array
    {
        return [
            'General' => ['cleanliness' => 'Overall cleanliness', 'walls_paint' => 'Walls and paint', 'ceilings' => 'Ceilings', 'floors_tiles' => 'Floors and tiles', 'doors' => 'Doors', 'windows' => 'Windows'],
            'Electrical' => ['sockets' => 'Sockets', 'switches' => 'Switches', 'lights' => 'Lights', 'meter' => 'Meter'],
            'Plumbing' => ['taps' => 'Taps', 'sinks' => 'Sinks', 'toilets' => 'Toilets', 'showers' => 'Showers', 'water_supply' => 'Water supply'],
            'Kitchen' => ['cabinets' => 'Cabinets', 'kitchen_sink' => 'Kitchen sink', 'countertop' => 'Countertop'],
            'Bedrooms' => ['bedroom_doors' => 'Bedroom doors', 'bedroom_windows' => 'Bedroom windows', 'wardrobe' => 'Wardrobe if applicable'],
            'Exterior / compound' => ['entrance' => 'Entrance', 'gate' => 'Gate', 'drainage' => 'Drainage', 'external_condition' => 'External condition'],
        ];
    }

    public function createForOccupancy(Occupancy $occupancy): MoveInConditionReport
    {
        abort_unless($occupancy->tenancyAgreement?->completed_at, 422, 'Complete the tenancy agreement before starting a move-in report.');

        return DB::transaction(function () use ($occupancy): MoveInConditionReport {
            $report = MoveInConditionReport::query()->firstOrCreate(['occupancy_id' => $occupancy->id], [
                'landlord_id' => $occupancy->property->landlord_id,
                'tenant_id' => $occupancy->tenant_id,
                'property_id' => $occupancy->property_id,
                'status' => 'draft',
            ]);

            foreach (self::checklist() as $category => $items) {
                foreach ($items as $key => $label) {
                    $report->items()->firstOrCreate(['item_key' => $key], ['category' => $category, 'label' => $label]);
                }
            }

            return $report->fresh(['items', 'photos']);
        });
    }
}
