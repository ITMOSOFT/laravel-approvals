<?php

namespace ItmoSoft\Approvals\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\Approvals\ApprovalManager;
use ItmoSoft\Approvals\Exceptions\ApprovalException;
use ItmoSoft\Approvals\Models\ApprovalRequest;
use ItmoSoft\Approvals\Tests\Fixtures\Purchase;
use ItmoSoft\Approvals\Tests\Fixtures\User;
use ItmoSoft\Approvals\Tests\TestCase;
use LogicException;

class ApprovalManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
        });

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_it_creates_a_sequential_approval_request(): void
    {
        [$subject, $requester, $first, $second] = $this->records();
        $request = app(ApprovalManager::class)->create($subject, 'purchase', $requester, [
            ['approver' => $first],
            ['approver' => $second],
        ], ['amount' => 125]);

        $this->assertSame('pending', $request->status);
        $this->assertSame(2, $request->steps->count());
        $this->assertSame(125, $request->payload['amount']);
        $this->assertSame(1, $request->current_step);
    }

    public function test_it_moves_to_the_next_step_after_approval(): void
    {
        [$subject, $requester, $first, $second] = $this->records();
        $manager = app(ApprovalManager::class);
        $request = $manager->create($subject, 'purchase', $requester, [
            ['approver' => $first], ['approver' => $second],
        ]);

        $request = $manager->approve($request, $first, 'Looks good');

        $this->assertSame('pending', $request->status);
        $this->assertSame(2, $request->current_step);
        $this->assertSame('approved', $request->steps[0]->status);
        $this->assertSame('Looks good', $request->steps[0]->comment);
    }

    public function test_it_completes_after_the_final_approval(): void
    {
        [$subject, $requester, $first, $second] = $this->records();
        $manager = app(ApprovalManager::class);
        $request = $manager->create($subject, 'purchase', $requester, [
            ['approver' => $first], ['approver' => $second],
        ]);

        $manager->approve($request, $first);
        $request = $request->fresh();
        $request = $manager->approve($request, $second);

        $this->assertSame('approved', $request->status);
        $this->assertNotNull($request->completed_at);
    }

    public function test_it_rejects_a_request_at_the_current_step(): void
    {
        [$subject, $requester, $first] = $this->records();
        $manager = app(ApprovalManager::class);
        $request = $manager->create($subject, 'purchase', $requester, [
            ['approver' => $first],
        ]);

        $request = $manager->reject($request, $first, 'Insufficient details');

        $this->assertSame('rejected', $request->status);
        $this->assertSame('rejected', $request->steps[0]->status);
    }

    public function test_it_prevents_an_unassigned_user_from_deciding(): void
    {
        [$subject, $requester, $first, $second] = $this->records();
        $request = app(ApprovalManager::class)->create($subject, 'purchase', $requester, [
            ['approver' => $first],
        ]);

        $this->expectException(ApprovalException::class);
        app(ApprovalManager::class)->approve($request, $second);
    }

    public function test_it_allows_the_requester_to_cancel(): void
    {
        [$subject, $requester, $first] = $this->records();
        $manager = app(ApprovalManager::class);
        $request = $manager->create($subject, 'purchase', $requester, [
            ['approver' => $first],
        ]);

        $request = $manager->cancel($request, $requester);

        $this->assertSame('cancelled', $request->status);
    }

    public function test_it_rejects_an_empty_step_list(): void
    {
        [$subject, $requester] = $this->records();

        $this->expectException(ApprovalException::class);
        app(ApprovalManager::class)->create($subject, 'purchase', $requester, []);
    }

    public function test_it_prevents_decisions_on_completed_requests(): void
    {
        [$subject, $requester, $first] = $this->records();
        $manager = app(ApprovalManager::class);
        $request = $manager->create($subject, 'purchase', $requester, [
            ['approver' => $first],
        ]);
        $request = $manager->approve($request, $first);

        $this->expectException(ApprovalException::class);
        $manager->approve($request, $first);
    }

    public function test_it_rejects_unsaved_subject_requester_and_approver_models(): void
    {
        [$subject, $requester, $first] = $this->records();
        $manager = app(ApprovalManager::class);

        try {
            $manager->create(new Purchase(['reference' => 'NEW']), 'purchase', $requester, [
                ['approver' => $first],
            ]);
            $this->fail('Unsaved subject should be rejected.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        try {
            $manager->create($subject, 'purchase', new User(['name' => 'New requester']), [
                ['approver' => $first],
            ]);
            $this->fail('Unsaved requester should be rejected.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        try {
            $manager->create($subject, 'purchase', $requester, [
                ['approver' => new User(['name' => 'New approver'])],
            ]);
            $this->fail('Unsaved approver should be rejected.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    public function test_it_rejects_invalid_workflow_key_and_payload(): void
    {
        [$subject, $requester, $first] = $this->records();
        $manager = app(ApprovalManager::class);

        try {
            $manager->create($subject, str_repeat('x', 121), $requester, [['approver' => $first]]);
            $this->fail('Oversized workflow key should be rejected.');
        } catch (ApprovalException) {
            $this->assertTrue(true);
        }

        $resource = fopen('php://memory', 'r');
        try {
            $manager->create($subject, 'purchase', $requester, [['approver' => $first]], ['bad' => $resource]);
            $this->fail('Non-JSON payload should be rejected.');
        } catch (ApprovalException) {
            $this->assertTrue(true);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function test_it_uses_configured_status_values(): void
    {
        config([
            'itmosoft-approvals.statuses.pending' => 'waiting',
            'itmosoft-approvals.statuses.approved' => 'accepted',
            'itmosoft-approvals.statuses.rejected' => 'declined',
            'itmosoft-approvals.statuses.cancelled' => 'withdrawn',
        ]);

        [$subject, $requester, $first] = $this->records();
        $manager = app(ApprovalManager::class);
        $request = $manager->create($subject, 'purchase', $requester, [['approver' => $first]]);

        $this->assertSame('waiting', $request->status);
        $request = $manager->approve($request, $first);
        $this->assertSame('accepted', $request->status);
    }

    private function records(): array
    {
        $requester = User::query()->create(['name' => 'Requester']);
        $first = User::query()->create(['name' => 'Approver One']);
        $second = User::query()->create(['name' => 'Approver Two']);
        $subject = Purchase::query()->create(['reference' => 'PO-100']);

        return [$subject, $requester, $first, $second];
    }
}
