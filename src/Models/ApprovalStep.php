<?php

namespace ItmoSoft\Approvals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approver(): MorphTo
    {
        return $this->morphTo();
    }
}
