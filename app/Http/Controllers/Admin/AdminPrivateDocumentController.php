<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPrivateDocumentController extends Controller
{
    public function landlordDocument(LandlordProfile $landlordProfile, LandlordDocument $landlordDocument): StreamedResponse
    {
        abort_unless($landlordDocument->landlord_profile_id === $landlordProfile->id, 404);
        abort_unless(Storage::disk('local')->exists($landlordDocument->file_path), 404);

        return Storage::disk('local')->download(
            $landlordDocument->file_path,
            $landlordDocument->original_name,
        );
    }

    public function propertyDocument(Property $property, PropertyDocument $propertyDocument): StreamedResponse
    {
        abort_unless($propertyDocument->property_id === $property->id, 404);
        abort_unless(Storage::disk('local')->exists($propertyDocument->file_path), 404);

        return Storage::disk('local')->download(
            $propertyDocument->file_path,
            $propertyDocument->original_name ?? basename($propertyDocument->file_path),
        );
    }
}
