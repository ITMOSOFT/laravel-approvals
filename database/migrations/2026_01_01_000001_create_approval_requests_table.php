<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('itmosoft-approvals.table_names.requests', 'approval_requests'), function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('subject');
            $table->nullableMorphs('requester');
            $table->string('workflow_key', 120);
            $table->string('status', 24)->default(config('itmosoft-approvals.statuses.pending', 'pending'))->index();
            $table->unsignedInteger('current_step')->default(1);
            $table->json('payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['workflow_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('itmosoft-approvals.table_names.requests', 'approval_requests'));
    }
};
