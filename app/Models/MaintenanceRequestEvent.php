<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; class MaintenanceRequestEvent extends Model {protected $fillable=['maintenance_request_id','actor_id','event_type','note']; public function request():BelongsTo{return $this->belongsTo(MaintenanceRequest::class,'maintenance_request_id');}}
