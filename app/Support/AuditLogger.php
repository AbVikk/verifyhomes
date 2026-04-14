<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\InspectionRequest;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditLogger
{
    public static function log(
        string $action,
        ?User $actor = null,
        Model|string|null $target = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'action' => $action,
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'target_type' => $target instanceof Model ? $target::class : (is_string($target) ? 'string' : null),
            'target_id' => $target instanceof Model ? $target->getKey() : null,
            'target_label' => static::targetLabel($target),
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    protected static function targetLabel(Model|string|null $target): ?string
    {
        if (is_string($target)) {
            return $target;
        }

        return match (true) {
            $target instanceof Property => $target->title,
            $target instanceof LandlordProfile => $target->business_name ?: $target->user?->name ?: 'Landlord profile #'.$target->getKey(),
            $target instanceof LandlordDocument => $target->original_name ?: str($target->document_type)->headline()->toString(),
            $target instanceof PropertyDocument => $target->original_name ?: str($target->document_type)->headline()->toString(),
            $target instanceof InspectionRequest => $target->property?->title
                ? 'Inspection request for '.$target->property->title
                : 'Inspection request #'.$target->getKey(),
            $target instanceof User => $target->name ?: $target->email,
            $target instanceof Model => class_basename($target).' #'.$target->getKey(),
            default => null,
        };
    }
}
