<?php
namespace App\Http\Controllers; use App\Models\MaintenanceRequestPhoto; use Illuminate\Support\Facades\Storage;
class MaintenancePhotoController extends Controller {public function show(MaintenanceRequestPhoto $photo){$r=$photo->request; $id=auth()->id(); abort_unless($r->tenant_id===$id||$r->landlord_id===$id||auth()->user()?->hasAnyRole(['admin','staff']),404);abort_unless(Storage::disk('local')->exists($photo->file_path),404);return Storage::disk('local')->response($photo->file_path,null,['Content-Disposition'=>'inline','Cache-Control'=>'private, no-store']);}}
