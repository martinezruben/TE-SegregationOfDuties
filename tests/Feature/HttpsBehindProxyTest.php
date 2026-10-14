<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;
use Tighten\Ziggy\BladeRouteGenerator;

/**
 * Detrás de un proxy que termina HTTPS, las URLs generadas (redirecciones y rutas de Ziggy que usa el
 * formulario de login) deben salir en https://. Si salen en http://, el navegador envía el login a otro
 * origen, sin token CSRF ni cookies `secure`, y el usuario ve un 419.
 */
class HttpsBehindProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ziggy solo emite sus rutas una vez por proceso; sin esto, los renders posteriores salen vacíos.
        BladeRouteGenerator::$generated = false;
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    /** Aplica la configuración y vuelve a ejecutar el arranque del proveedor, como en un despliegue. */
    private function boot(array $config): void
    {
        config($config);
        (new AppServiceProvider($this->app))->boot();
    }

    private function location(array $headers = []): string
    {
        return $this->withHeaders($headers)->get('/')->headers->get('Location');
    }

    private function ziggyUrl(): string
    {
        preg_match('/"url":"([^"]+)"/', $this->get('/login')->getContent(), $m);

        return stripslashes($m[1] ?? '');
    }

    public function test_by_default_urls_follow_the_request_scheme(): void
    {
        $this->boot(['app.url' => 'http://localhost']);

        $this->assertStringStartsWith('http://', $this->location());
        $this->assertStringStartsWith('http://', $this->ziggyUrl());
    }

    public function test_an_https_app_url_forces_https_in_redirects_and_ziggy(): void
    {
        $this->boot(['app.url' => 'https://sod.example.com']);

        $this->assertStringStartsWith('https://', $this->location());
        $this->assertStringStartsWith('https://', $this->ziggyUrl(), 'La ruta que usa el formulario de login debe ser https.');
    }

    public function test_force_https_can_be_disabled_explicitly(): void
    {
        $this->boot(['app.url' => 'https://sod.example.com', 'sod.http.force_https' => 'false']);

        $this->assertStringStartsWith('http://', $this->location());
    }

    public function test_force_https_can_be_enabled_without_an_https_app_url(): void
    {
        $this->boot(['app.url' => 'http://localhost', 'sod.http.force_https' => 'true']);

        $this->assertStringStartsWith('https://', $this->location());
    }

    public function test_forwarded_proto_is_ignored_unless_the_proxy_is_trusted(): void
    {
        $this->boot(['app.url' => 'http://localhost']);

        // Un cliente cualquiera no puede cambiar el esquema con una cabecera.
        $this->assertStringStartsWith('http://', $this->location(['X-Forwarded-Proto' => 'https']));
    }

    public function test_trusted_proxies_make_laravel_honor_forwarded_proto(): void
    {
        $this->boot(['app.url' => 'http://localhost', 'sod.http.trusted_proxies' => '*']);

        $this->assertStringStartsWith('https://', $this->location(['X-Forwarded-Proto' => 'https']));
    }

    public function test_trusted_proxies_accept_a_comma_separated_list(): void
    {
        // El cliente de las pruebas llega desde 127.0.0.1.
        $this->boot(['app.url' => 'http://localhost', 'sod.http.trusted_proxies' => '10.0.0.1, 127.0.0.1']);

        $this->assertStringStartsWith('https://', $this->location(['X-Forwarded-Proto' => 'https']));
    }

    public function test_a_proxy_that_is_not_in_the_list_is_not_trusted(): void
    {
        $this->boot(['app.url' => 'http://localhost', 'sod.http.trusted_proxies' => '10.0.0.1']);

        $this->assertStringStartsWith('http://', $this->location(['X-Forwarded-Proto' => 'https']));
    }
}
