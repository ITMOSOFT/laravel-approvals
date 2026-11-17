<?php

namespace ItmoSoft\Approvals;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ItmoSoft\Approvals\Exceptions\ApprovalException;
use ItmoSoft\Approvals\Models\ApprovalRequest;
use ItmoSoft\Approvals\Models\ApprovalStep;

class ApprovalManager
{
    /**
     * Create a sequential approval request.
     *
     * Each step must contain an Eloquent approver model under the "approver" key.
     *
     * @param array<int, array{approver: Model}> $steps
     */
    public function create(
        Model $subject,
        string $workflowKey,
        Model $requester,
        array $steps,
        array $payload = [],
    ): ApprovalRequest {
        if (trim($workflowKey) === '') {
            throw new ApprovalException('The workflow key cannot be empty.');
        }

        if ($steps === []) {
            throw new ApprovalException('At least one approval step is required.');
        }

        foreach ($steps as $step) {
            if (! isset($step['approver']) || ! $step['approver'] instanceof Model) {
                throw new ApprovalException('Every step must contain an Eloquent approver model.');
            }
        }

        return DB::transaction(function () use ($subject, $workflowKey, $requester, $steps, $payload) {
            $request = ApprovalRequest::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'requester_type' => $requester->getMorphClass(),
                'requester_id' => $requester->getKey(),
                'workflow_key' => $workflowKey,
                'status' => 'pending',
                'current_step' => 1,
                'payload' => $payload,
                'submitted_at' => now(),
            ]);

            foreach (array_values($steps) as $index => $step) {
                $approver = $step['approver'];

                $request->steps()->create([
                    'step_number' => $index + 1,
                    'approver_type' => $approver->getMorphClass(),
                    'approver_id' => $approver->getKey(),
                    'status' => 'pending',
                ]);
            }

            return $request->load('steps');
        });
    }

    public function approve(ApprovalRequest $request, Model $approver, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $comment) {
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $step = $locked->steps()->where('step_number', $locked->current_step)->lockForUpdate()->first();
            $this->assertAssignedApprover($step, $approver);

            $step->forceFill([
                'status' => 'approved',
                'comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $next = $locked->steps()->where('step_number', '>', $locked->current_step)
                ->orderBy('step_number')->first();

            if ($next) {
                $locked->current_step = $next->step_number;
            } else {
                $locked->status = 'approved';
                $locked->completed_at = now();
            }

            $locked->save();

            return $locked->load('steps');
        });
    }

    public function reject(ApprovalRequest $request, Model $approver, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $comment) {
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $step = $locked->steps()->where('step_number', $locked->current_step)->lockForUpdate()->first();
            $this->assertAssignedApprover($step, $approver);

            $step->forceFill([
                'status' => 'rejected',
                'comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $locked->status = 'rejected';
            $locked->completed_at = now();
            $locked->save();

            return $locked->load('steps');
        });
    }

    public function cancel(ApprovalRequest $request, Model $requester): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $requester) {
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            if ($locked->requester_type !== $requester->getMorphClass()
                || (string) $locked->requester_id !== (string) $requester->getKey()) {
                throw new ApprovalException('Only the original requester may cancel this request.');
            }

            $locked->status = 'cancelled';
            $locked->completed_at = now();
            $locked->save();

            return $locked->load('steps');
        });
    }

    private function assertPending(ApprovalRequest $request): void
    {
        if (! $request->isPending()) {
            throw new ApprovalException('This approval request is no longer pending.');
        }
    }

    private function assertAssignedApprover(?ApprovalStep $step, Model $approver): void
    {
        if (! $step
            || $step->approver_type !== $approver->getMorphClass()
            || (string) $step->approver_id !== (string) $approver->getKey()) {
            throw new ApprovalException('This user is not assigned to the current approval step.');
        }

        if ($step->status !== 'pending') {
            throw new ApprovalException('The current approval step has already been decided.');
        }
    }
}
