<?php

namespace App\Http\Controllers;

use App\Models\MoveInConditionPhoto;
use Illuminate\Support\Facades\Storage;

class MoveInConditionPhotoController extends Controller
{
    public function tenant(MoveInConditionPhoto $photo)
    {
        abort_unless($photo->report->tenant_id === auth()->id(), 404);
        return $this->response($photo);
    }

    public function landlord(MoveInConditionPhoto $photo)
    {
        abort_unless($photo->report->landlord_id === auth()->id(), 404);
        return $this->response($photo);
    }

    public function admin(MoveInConditionPhoto $photo)
    {
        return $this->response($photo);
    }

    private function response(MoveInConditionPhoto $photo)
    {
        abort_unless(Storage::disk('local')->exists($photo->file_path), 404);

        return Storage::disk('local')->response($photo->file_path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
