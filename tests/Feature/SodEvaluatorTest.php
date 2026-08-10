<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\Role;
use App\Models\SodConflict;
use App\Services\SodEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SodEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private SodEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(SodEvaluator::class);
    }

    private function scenario(): array
    {
        $area = Area::create(['name' => 'Fin', 'code' => 'FIN']);
        $generic = JobPosition::create(['name' => 'Analista (gen)', 'is_generic' => true]);
        $position = JobPosition::create(['name' => 'Analista Fin', 'generic_position_id' => $generic->id]);
        $app = Application::create(['name' => 'ERP', 'criticality_level' => 'alta']);
        $crear = Role::create(['application_id' => $app->id, 'name' => 'Crear Proveedor']);
        $aprobar = Role::create(['application_id' => $app->id, 'name' => 'Aprobar Pago']);
        $user = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'I1', 'name' => 'María',
            'area_id' => $area->id, 'job_position_id' => $position->id,
        ]);

        return compact('generic', 'position', 'app', 'crear', 'aprobar', 'user');
    }

    public function test_cargo_permitido_allows(): void
    {
        ['position' => $p, 'crear' => $crear, 'user' => $user] = $this->scenario();
        $p->roleRules()->attach($crear->id, ['rule_type' => 'permitido']);

        $r = $this->evaluator->evaluate($user->fresh(), $crear);
        $this->assertSame('permitido', $r['cargo']['status']);
        $this->assertTrue($r['cargo']['allowed']);
        $this->assertTrue($r['viable']);
    }

    public function test_cargo_no_permitido_blocks(): void
    {
        ['position' => $p, 'crear' => $crear, 'user' => $user] = $this->scenario();
        $p->roleRules()->attach($crear->id, ['rule_type' => 'no_permitido']);

        $r = $this->evaluator->evaluate($user->fresh(), $crear);
        $this->assertSame('no_permitido', $r['cargo']['status']);
        $this->assertFalse($r['viable']);
    }

    public function test_generic_rule_applies(): void
    {
        ['generic' => $g, 'crear' => $crear, 'user' => $user] = $this->scenario();
        $g->roleRules()->attach($crear->id, ['rule_type' => 'permitido']);

        $r = $this->evaluator->evaluate($user->fresh(), $crear);
        $this->assertTrue($r['cargo']['allowed']); // heredado del genérico
    }

    public function test_no_rule_defaults_to_not_allowed(): void
    {
        ['crear' => $crear, 'user' => $user] = $this->scenario();

        $r = $this->evaluator->evaluate($user->fresh(), $crear);
        $this->assertSame('sin_regla', $r['cargo']['status']);
        $this->assertFalse($r['cargo']['allowed']);
    }

    public function test_detects_sod_conflict_against_foto(): void
    {
        ['position' => $p, 'crear' => $crear, 'aprobar' => $aprobar, 'user' => $user] = $this->scenario();
        // Usuario ya tiene "Crear Proveedor" (foto) y el cargo permite "Aprobar Pago".
        $user->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'import']);
        $p->roleRules()->attach($aprobar->id, ['rule_type' => 'permitido']);
        // Conflicto en la matriz (orden normalizado).
        SodConflict::create([
            'role_a_id' => min($crear->id, $aprobar->id),
            'role_b_id' => max($crear->id, $aprobar->id),
            'risk_level' => 'alto',
        ]);

        $r = $this->evaluator->evaluate($user->fresh(), $aprobar);
        $this->assertTrue($r['has_conflict']);
        $this->assertCount(1, $r['conflicts']);
        $this->assertSame('Crear Proveedor', $r['conflicts'][0]['role_name']);
        $this->assertSame('alto', $r['conflicts'][0]['risk_level']);
        $this->assertFalse($r['viable']); // permitido por cargo, pero hay conflicto
    }
}
