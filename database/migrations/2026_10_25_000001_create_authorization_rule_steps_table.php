<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una regla puede exigir varias autorizaciones en secuencia (step_order).
        Schema::create('authorization_rule_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_rule_id')->constrained('authorization_rules')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('authorizer_type')->default('fixed'); // fixed|supervisor
            $table->foreignId('authorizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['authorization_rule_id', 'step_order']);
        });

        // Las reglas existentes pasan a tener un único paso.
        DB::table('authorization_rules')->orderBy('id')->each(function ($rule) {
            DB::table('authorization_rule_steps')->insert([
                'authorization_rule_id' => $rule->id,
                'step_order' => 1,
                'authorizer_type' => $rule->authorizer_type,
                'authorizer_id' => $rule->authorizer_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('authorization_rules', function (Blueprint $table) {
            $table->dropForeign(['authorizer_id']);
            $table->dropColumn(['authorizer_type', 'authorizer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('authorization_rules', function (Blueprint $table) {
            $table->string('authorizer_type')->default('fixed');
            $table->foreignId('authorizer_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Se conserva solo el primer paso de cada regla.
        DB::table('authorization_rule_steps')->where('step_order', 1)->orderBy('id')->each(function ($step) {
            DB::table('authorization_rules')->where('id', $step->authorization_rule_id)->update([
                'authorizer_type' => $step->authorizer_type,
                'authorizer_id' => $step->authorizer_id,
            ]);
        });

        Schema::dropIfExists('authorization_rule_steps');
    }
};
