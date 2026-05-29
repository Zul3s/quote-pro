<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            // Soft gate: the prospect acknowledged they cannot provide the
            // missing details, so the sufficiency guard was skipped.
            $table->boolean('missing_info_acknowledged')->default(false);

            // Qualification labels — all nullable; qualified_at IS NULL means
            // "not yet qualified". Urgency is NOT a column: it rides on deadline.
            $table->string('relevance')->nullable();
            $table->string('project_type')->nullable();
            $table->string('summary')->nullable();
            $table->integer('estimated_amount_min')->nullable();
            $table->integer('estimated_amount_max')->nullable();
            $table->string('lead_quality')->nullable();
            $table->string('priority')->nullable();
            $table->timestamp('qualified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropColumn([
                'missing_info_acknowledged',
                'relevance',
                'project_type',
                'summary',
                'estimated_amount_min',
                'estimated_amount_max',
                'lead_quality',
                'priority',
                'qualified_at',
            ]);
        });
    }
};
