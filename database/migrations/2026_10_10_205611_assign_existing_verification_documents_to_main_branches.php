<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BRANCH_LEVEL_TYPES = [
        'bir_certificate',
        'business_permit',
    ];

    public function up(): void
    {
        $dueAt = now()->addDays(30);

        DB::table('spas')
            ->orderBy('id')
            ->chunkById(100, function ($spas) use ($dueAt) {
                foreach ($spas as $spa) {
                    // Main branch = the one flagged is_main,
                    // otherwise the oldest branch.
                    $mainBranchId = DB::table('branches')
                        ->where('spa_id', $spa->id)
                        ->whereNull('deleted_at')
                        ->orderByDesc('is_main')
                        ->orderBy('id')
                        ->value('id');

                    if (! $mainBranchId) {
                        continue;
                    }

                    // BIR and Business Permit now belong to the main branch.
                    // Government ID and DTI/SEC stay on the spa (branch_id NULL).
                    foreach ([
                        'spa_verification_documents',
                        'spa_verification_document_histories',
                    ] as $table) {
                        DB::table($table)
                            ->where('spa_id', $spa->id)
                            ->whereNull('branch_id')
                            ->whereIn('document_type', self::BRANCH_LEVEL_TYPES)
                            ->update(['branch_id' => $mainBranchId]);
                    }

                    // The main branch takes the spa's current status.
                    DB::table('branches')
                        ->where('id', $mainBranchId)
                        ->update([
                            'verification_status' => $spa->verification_status ?: 'unverified',
                            'verification_remarks' => $spa->verification_remarks,
                            'verified_at' => $spa->verified_at,
                            'verified_by' => $spa->verified_by,
                            'verification_due_at' => null,
                        ]);

                    // Every other existing branch gets 30 days to submit documents.
                    DB::table('branches')
                        ->where('spa_id', $spa->id)
                        ->where('id', '!=', $mainBranchId)
                        ->whereNull('deleted_at')
                        ->update([
                            'verification_status' => 'unverified',
                            'verification_due_at' => $dueAt,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('spa_verification_documents')
            ->update(['branch_id' => null]);

        DB::table('spa_verification_document_histories')
            ->update(['branch_id' => null]);

        DB::table('branches')->update([
            'verification_status' => 'unverified',
            'verification_remarks' => null,
            'verified_at' => null,
            'verified_by' => null,
            'verification_due_at' => null,
        ]);
    }
};
