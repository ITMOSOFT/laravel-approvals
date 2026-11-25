<?php

namespace ItmoSoft\Approvals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class ApprovalStep extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('itmosoft-approvals.table_names.steps', 'approval_steps');
    }

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $step): void {
            if ((int) $step->step_number < 1) {
                throw new InvalidArgumentException('Approval step number must be at least 1.');
            }

            if (! is_string($step->status) || trim($step->status) === '' || mb_strlen($step->status) > 24) {
                throw new InvalidArgumentException('Approval step status is required and must not exceed 24 characters.');
            }
        });
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approver(): MorphTo
    {
        return $this->morphTo();
    }
}
