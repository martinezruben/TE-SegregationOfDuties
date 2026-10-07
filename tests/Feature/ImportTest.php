<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Area;
use App\Models\ImportBatch;
use App\Models\JobPosition;
use App\Models\ManagedUser;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Application $erp;

    private ManagedUser $maria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrador');

        $area = Area::create(['name' => 'Finanzas', 'code' => 'FIN']);
        $cargo = JobPosition::create(['name' => 'Analista']);
        $this->erp = Application::create(['name' => 'ERP', 'criticality_level' => 'media']);
        $this->erp->roles()->create(['name' => 'Crear Proveedor']);
        $this->erp->roles()->create(['name' => 'Aprobar Pago']);
        $this->maria = ManagedUser::create([
            'external_id' => 'E1', 'igg' => 'IGG001', 'name' => 'María',
            'area_id' => $area->id, 'job_position_id' => $cargo->id,
        ]);
    }

    private function csv(string $content, string $name = 'datos.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function preview(string $kind, string $csv, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post('/importaciones', ['kind' => $kind, 'file' => $this->csv($csv)]);
    }

    private function lote(): ImportBatch
    {
        return ImportBatch::latest('id')->firstOrFail();
    }

    // ---------- permisos ----------

    public function test_only_authorized_agents_can_access(): void
    {
        foreach (['Consultor', 'Solicitante', 'Auditor'] as $role) {
            $u = User::factory()->create();
            $u->assignRole($role);
            $this->actingAs($u)->get('/importaciones')->assertForbidden();
        }

        $this->actingAs($this->admin)->get('/importaciones')->assertOk();
    }

    public function test_kind_permission_is_required_on_top_of_importaciones_crear(): void
    {
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::findOrCreate('importaciones.ver', 'web'), 'importaciones.crear');

        // Tiene importaciones.crear pero no foto.editar ni gestionados.crear.
        $this->preview('foto', "igg,aplicacion,rol\nIGG001,ERP,Crear Proveedor", $u)->assertForbidden();
        $this->preview('gestionados', "id,igg\nX,Y", $u)->assertForbidden();

        $u->givePermissionTo('foto.editar');
        $this->preview('foto', "igg,aplicacion,rol\nIGG001,ERP,Crear Proveedor", $u)->assertRedirect();
    }

    public function test_unknown_kind_and_invalid_file_are_rejected(): void
    {
        $this->actingAs($this->admin)->post('/importaciones', ['kind' => 'otra', 'file' => $this->csv("a\n1")])->assertNotFound();
        $this->actingAs($this->admin)->post('/importaciones', ['kind' => 'foto', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post('/importaciones', ['kind' => 'foto', 'file' => $this->csv("igg,aplicacion,rol\n")])
            ->assertSessionHasErrors('file');
    }

    // ---------- foto ----------

    public function test_foto_preview_is_a_dry_run_and_reports_row_errors(): void
    {
        $csv = implode("\n", [
            'igg,aplicacion,rol,fecha_asignacion,expira',
            'IGG001,ERP,Crear Proveedor,2026-01-15,',                       // 2 ok
            'IGG999,ERP,Crear Proveedor,,',                                // 3 usuario no existe
            'IGG001,Otra,Crear Proveedor,,',                               // 4 app no existe
            'IGG001,ERP,Rol Fantasma,,',                                   // 5 rol no existe
            'IGG001,ERP,Aprobar Pago,15/13/2026,',                          // 6 fecha inválida
            'IGG001,ERP,Aprobar Pago,2026-05-01,2026-04-01',                // 7 expira < asignación
            'IGG001,ERP,Crear Proveedor,,',                                // 8 duplicado
            ',ERP,Aprobar Pago,,',                                          // 9 falta igg
        ]);

        $this->preview('foto', $csv)->assertRedirect();

        $lote = $this->lote();
        $this->assertSame('preview', $lote->status);
        $this->assertSame(['crear' => 1, 'actualizar' => 0, 'sin_cambios' => 0, 'error' => 7], $lote->summary);
        $this->assertSame([3, 4, 5, 6, 7, 8, 9], array_column($lote->errors, 'line'));
        $this->assertStringContainsString('no existe', $lote->errors[0]['message']);
        $this->assertStringContainsString('Duplicado', $lote->errors[5]['message']);
        $this->assertSame(0, \DB::table('user_role')->count(), 'El dry-run no debe escribir en la foto.');
    }

    public function test_foto_apply_creates_updates_and_skips_errors(): void
    {
        $crear = $this->erp->roles()->where('name', 'Crear Proveedor')->first();
        $aprobar = $this->erp->roles()->where('name', 'Aprobar Pago')->first();
        $this->maria->roles()->attach($aprobar->id, ['assigned_at' => now(), 'source' => 'manual', 'expires_at' => null]);

        $this->preview('foto', implode("\n", [
            'igg,aplicacion,rol,fecha_asignacion,expira',
            'IGG001,ERP,Crear Proveedor,2026-01-15,2027-01-15',   // crear
            'IGG001,ERP,Aprobar Pago,,2026-12-31',                // actualizar (cambia expiración)
            'IGG404,ERP,Aprobar Pago,,',                          // error
        ]));
        $lote = $this->lote();
        $this->assertSame(1, $lote->summary['crear']);
        $this->assertSame(1, $lote->summary['actualizar']);

        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar")
            ->assertRedirect("/importaciones/{$lote->id}")
            ->assertSessionHas('success');

        $row = \DB::table('user_role')->where('role_id', $crear->id)->first();
        $this->assertSame('import', $row->source);
        $this->assertSame($this->admin->id, (int) $row->assigned_by);
        $this->assertStringStartsWith('2026-01-15', $row->assigned_at);
        $this->assertStringStartsWith('2027-01-15', $row->expires_at);

        $updated = \DB::table('user_role')->where('role_id', $aprobar->id)->first();
        $this->assertStringStartsWith('2026-12-31', $updated->expires_at);
        $this->assertSame('manual', $updated->source, 'Actualizar vigencia no cambia el origen.');

        $lote->refresh();
        $this->assertSame('applied', $lote->status);
        $this->assertSame(['creados' => 1, 'actualizados' => 1, 'sin_cambios' => 0, 'errores' => 1], $lote->result);
        $this->assertSame([], $lote->raw_rows);
    }

    public function test_batch_cannot_be_applied_twice(): void
    {
        $this->preview('foto', "igg,aplicacion,rol\nIGG001,ERP,Crear Proveedor");
        $lote = $this->lote();

        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar");
        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar")->assertSessionHas('error');

        $this->assertSame(1, \DB::table('user_role')->count());
    }

    public function test_apply_revalidates_against_current_data(): void
    {
        $this->preview('foto', "igg,aplicacion,rol\nIGG001,ERP,Crear Proveedor");
        $lote = $this->lote();

        // Entre la vista previa y la confirmación el usuario fue eliminado.
        $this->maria->delete();

        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar")->assertSessionHas('error');
        $this->assertSame(0, \DB::table('user_role')->count());
        $this->assertSame('preview', $lote->fresh()->status);
    }

    public function test_foto_unchanged_rows_need_no_apply(): void
    {
        $crear = $this->erp->roles()->where('name', 'Crear Proveedor')->first();
        $this->maria->roles()->attach($crear->id, ['assigned_at' => now(), 'source' => 'manual']);

        $this->preview('foto', "igg,aplicacion,rol\nIGG001,erp,crear proveedor"); // insensible a mayúsculas
        $lote = $this->lote();

        $this->assertSame(1, $lote->summary['sin_cambios']);
        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar")->assertSessionHas('error');
    }

    // ---------- gestionados ----------

    public function test_gestionados_create_update_and_validate(): void
    {
        $boss = User::factory()->create(['email' => 'jefe@empresa.com']);

        $csv = implode("\n", [
            'id,igg,nombre,area,cargo,supervisor_email,activo',
            'E2,IGG002,Luis Gómez,FIN,Analista,jefe@empresa.com,si',    // 2 crear (área por código)
            'E1,IGG001,María Pérez,Finanzas,Analista,,no',               // 3 actualizar (nombre/activo; área por nombre)
            'E3,IGG003,Ana,XXX,Analista,,',                              // 4 área inexistente
            'E4,IGG004,Pedro,FIN,Astronauta,,',                          // 5 cargo inexistente
            'E5,IGG002,Duplicado,FIN,Analista,,',                        // 6 IGG duplicado en archivo
            'E1,IGG006,Choque,FIN,Analista,,',                           // 7 ID de otro usuario
            'E7,IGG007,Sin jefe,FIN,Analista,nadie@x.com,',              // 8 supervisor inexistente
            ',IGG008,Sin id,FIN,Analista,,',                             // 9 falta id
        ]);

        $this->preview('gestionados', $csv)->assertRedirect();
        $lote = $this->lote();

        $this->assertSame(['crear' => 1, 'actualizar' => 1, 'sin_cambios' => 0, 'error' => 6], $lote->summary);
        $this->assertSame([4, 5, 6, 7, 8, 9], array_column($lote->errors, 'line'));
        $this->assertSame(1, ManagedUser::count(), 'El dry-run no crea usuarios.');

        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar")->assertRedirect();

        $luis = ManagedUser::where('igg', 'IGG002')->firstOrFail();
        $this->assertSame('Luis Gómez', $luis->name);
        $this->assertSame($boss->id, $luis->supervisor_id);

        $this->maria->refresh();
        $this->assertSame('María Pérez', $this->maria->name);
        $this->assertFalse($this->maria->is_active);
        $this->assertSame(2, ManagedUser::count());
    }

    public function test_semicolon_csv_with_accents_is_supported(): void
    {
        $csv = "id;igg;nombre;area;cargo\nE9;IGG009;José Ñandú;FIN;Analista";

        $this->preview('gestionados', $csv)->assertRedirect();

        $this->assertSame(1, $this->lote()->summary['crear']);
    }

    public function test_xlsx_with_excel_date_serials(): void
    {
        $content = Excel::raw(new class implements FromArray
        {
            public function array(): array
            {
                // 46037 = 2026-01-15 en serial de Excel
                return [['igg', 'aplicacion', 'rol', 'fecha_asignacion'], ['IGG001', 'ERP', 'Crear Proveedor', 46037]];
            }
        }, ExcelType::XLSX);

        $this->actingAs($this->admin)->post('/importaciones', [
            'kind' => 'foto',
            'file' => UploadedFile::fake()->createWithContent('foto.xlsx', $content),
        ])->assertRedirect();

        $lote = $this->lote();
        $this->assertSame(1, $lote->summary['crear']);

        $this->actingAs($this->admin)->post("/importaciones/{$lote->id}/aplicar");
        $this->assertStringStartsWith('2026-01-15', \DB::table('user_role')->value('assigned_at'));
    }

    // ---------- descargas y páginas ----------

    public function test_templates_and_error_report_downloads(): void
    {
        $tpl = $this->actingAs($this->admin)->get('/importaciones/plantilla/foto');
        $tpl->assertOk();
        $this->assertStringContainsString('igg,aplicacion,rol', $tpl->streamedContent());
        $this->actingAs($this->admin)->get('/importaciones/plantilla/nada')->assertNotFound();

        // Una celda maliciosa (fórmula) se neutraliza en el CSV de errores.
        $this->preview('foto', "igg,aplicacion,rol\n=HYPERLINK(\"http://x\"),ERP,Crear Proveedor");
        $csv = $this->actingAs($this->admin)->get("/importaciones/{$this->lote()->id}/errores")->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_pages_render_with_expected_props(): void
    {
        $this->preview('foto', "igg,aplicacion,rol\nIGG001,ERP,Crear Proveedor");
        $lote = $this->lote();

        $this->actingAs($this->admin)->get('/importaciones')->assertInertia(fn ($p) => $p
            ->component('Importaciones/Index')->has('tipos', 2)->has('lotes', 1));

        $this->actingAs($this->admin)->get("/importaciones/{$lote->id}")->assertInertia(fn ($p) => $p
            ->component('Importaciones/Show')->where('canApply', true)->where('lote.summary.crear', 1));
    }

    public function test_menu_item_is_registered_for_admin_only(): void
    {
        $this->seed(MenuSeeder::class);

        $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn ($p) => $p
            ->where('menu', fn ($menu) => collect($menu)->pluck('children')->flatten(1)->pluck('route')->contains('importaciones.index')));

        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');
        $this->actingAs($auditor)->get('/dashboard')->assertInertia(fn ($p) => $p
            ->where('menu', fn ($menu) => ! collect($menu)->pluck('children')->flatten(1)->pluck('route')->contains('importaciones.index')));
    }
}
