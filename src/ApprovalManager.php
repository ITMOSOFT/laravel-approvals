<?php

namespace ItmoSoft\Approvals;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ItmoSoft\Approvals\Exceptions\ApprovalException;
use ItmoSoft\Approvals\Models\ApprovalRequest;
use ItmoSoft\Approvals\Models\ApprovalStep;
use JsonException;
use LogicException;

class ApprovalManager
{
    /**
     * Create a sequential approval request.
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
        $this->assertPersisted($subject, 'subject');
        $this->assertPersisted($requester, 'requester');

        if (trim($workflowKey) === '' || mb_strlen($workflowKey) > 120) {
            throw new ApprovalException('The workflow key is required and must not exceed 120 characters.');
        }

        if ($steps === []) {
            throw new ApprovalException('At least one approval step is required.');
        }

        foreach ($steps as $step) {
            if (! is_array($step) || ! isset($step['approver']) || ! $step['approver'] instanceof Model) {
                throw new ApprovalException('Every step must contain an Eloquent approver model.');
            }

            $this->assertPersisted($step['approver'], 'approver');
        }

        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApprovalException('The approval payload must be JSON encodable.', previous: $exception);
        }

        $statuses = $this->statuses();

        return DB::transaction(function () use ($subject, $workflowKey, $requester, $steps, $payload, $statuses) {
            $request = ApprovalRequest::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'requester_type' => $requester->getMorphClass(),
                'requester_id' => $requester->getKey(),
                'workflow_key' => $workflowKey,
                'status' => $statuses['pending'],
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
                    'status' => $statuses['pending'],
                ]);
            }

            return $request->load('steps');
        });
    }

    public function approve(ApprovalRequest $request, Model $approver, ?string $comment = null): ApprovalRequest
    {
        $this->assertPersisted($request, 'approval request');
        $this->assertPersisted($approver, 'approver');
        $this->validateComment($comment);
        $statuses = $this->statuses();

        return DB::transaction(function () use ($request, $approver, $comment, $statuses) {
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($locked, $statuses);

            $step = $locked->steps()->where('step_number', $locked->current_step)->lockForUpdate()->first();
            $this->assertAssignedApprover($step, $approver, $statuses);

            $step->forceFill([
                'status' => $statuses['approved'],
                'comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $next = $locked->steps()->where('step_number', '>', $locked->current_step)
                ->orderBy('step_number')->first();

            if ($next) {
                $locked->current_step = $next->step_number;
            } else {
                $locked->status = $statuses['approved'];
                $locked->completed_at = now();
            }

            $locked->save();

            return $locked->load('steps');
        });
    }

    public function reject(ApprovalRequest $request, Model $approver, ?string $comment = null): ApprovalRequest
    {
        $this->assertPersisted($request, 'approval request');
        $this->assertPersisted($approver, 'approver');
        $this->validateComment($comment);
        $statuses = $this->statuses();

        return DB::transaction(function () use ($request, $approver, $comment, $statuses) {
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($locked, $statuses);

            $step = $locked->steps()->where('step_number', $locked->current_step)->lockForUpdate()->first();
            $this->assertAssignedApprover($step, $approver, $statuses);

            $step->forceFill([
                'status' => $statuses['rejected'],
                'comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $locked->status = $statuses['rejected'];
            $locked->completed_at = now();
            $locked->save();

            return $locked->load('steps');
        });
    }

    public function cancel(ApprovalRequest $request, Model $requester): ApprovalRequest
    {
        $this->assertPersisted($request, 'approval request');
        $this->assertPersisted($requester, 'requester');
        $statuses = $this->statuses();

        return DB::transaction(function () use ($request, $requester, $statuses) {
            $locked = ApprovalRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($locked, $statuses);

            if ($locked->requester_type !== $requester->getMorphClass()
                || (string) $locked->requester_id !== (string) $requester->getKey()) {
                throw new ApprovalException('Only the original requester may cancel this request.');
            }

            $locked->status = $statuses['cancelled'];
            $locked->completed_at = now();
            $locked->save();

            return $locked->load('steps');
        });
    }

    private function assertPending(ApprovalRequest $request, array $statuses): void
    {
        if ($request->status !== $statuses['pending']) {
            throw new ApprovalException('This approval request is no longer pending.');
        }
    }

    private function assertAssignedApprover(?ApprovalStep $step, Model $approver, array $statuses): void
    {
        if (! $step
            || $step->approver_type !== $approver->getMorphClass()
            || (string) $step->approver_id !== (string) $approver->getKey()) {
            throw new ApprovalException('This user is not assigned to the current approval step.');
        }

        if ($step->status !== $statuses['pending']) {
            throw new ApprovalException('The current approval step has already been decided.');
        }
    }

    private function assertPersisted(Model $model, string $label): void
    {
        if (! $model->exists || $model->getKey() === null) {
            throw new LogicException("The {$label} model must be persisted before it can be used.");
        }
    }

    private function validateComment(?string $comment): void
    {
        if ($comment !== null && ! is_string($comment)) {
            throw new ApprovalException('An approval comment must be a string or null.');
        }
    }

    /**
     * @return array{pending: string, approved: string, rejected: string, cancelled: string}
     */
    private function statuses(): array
    {
        $defaults = [
            'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
        ];
        $configured = config('itmosoft-approvals.statuses', []);

        foreach ($defaults as $key => $default) {
            $value = $configured[$key] ?? $default;
            if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 24) {
                throw new ApprovalException("The configured '{$key}' approval status must be a non-empty string of at most 24 characters.");
            }
            $defaults[$key] = $value;
        }

        if (count(array_unique($defaults)) !== count($defaults)) {
            throw new ApprovalException('Configured approval statuses must be unique.');
        }

        return $defaults;
    }
}
