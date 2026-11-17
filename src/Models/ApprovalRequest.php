<?php

namespace ItmoSoft\Approvals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->status === 'pending';
    }
}
