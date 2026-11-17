<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('itmosoft-approvals.table_names.steps', 'approval_steps'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained(config('itmosoft-approvals.table_names.requests', 'approval_requests'))->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->nullableMorphs('approver');
            $table->string('status', 24)->default('pending')->index();
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['approval_request_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('itmosoft-approvals.table_names.steps', 'approval_steps'));
    }
};
