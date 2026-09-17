<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; class MaintenanceRequestPhoto extends Model {protected $fillable=['maintenance_request_id','uploaded_by_id','source','file_path','original_name','file_size']; public function request():BelongsTo{return $this->belongsTo(MaintenanceRequest::class,'maintenance_request_id');}}
