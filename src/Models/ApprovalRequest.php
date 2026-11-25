<?php

namespace ItmoSoft\Approvals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use JsonException;
use InvalidArgumentException;

class ApprovalRequest extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('itmosoft-approvals.table_names.requests', 'approval_requests');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            if (! is_string($request->workflow_key) || trim($request->workflow_key) === '' || mb_strlen($request->workflow_key) > 120) {
                throw new InvalidArgumentException('Approval workflow key is required and must not exceed 120 characters.');
            }

            if (! is_string($request->status) || trim($request->status) === '' || mb_strlen($request->status) > 24) {
                throw new InvalidArgumentException('Approval request status is required and must not exceed 24 characters.');
            }

            if ((int) $request->current_step < 1) {
                throw new InvalidArgumentException('Approval current step must be at least 1.');
            }

            if ($request->payload !== null) {
                try {
                    json_encode($request->payload, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new InvalidArgumentException('Approval payload must be JSON encodable.', previous: $exception);
                }
            }
        });
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): MorphTo
    {
        return $this->morphTo('requester');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_number');
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps()->where('step_number', $this->current_step)->first();
    }

    public function isPending(): bool
    {
        return $this->status === config('itmosoft-approvals.statuses.pending', 'pending');
    }
}
