<?php

/**
 * HTTP-level integration tests for multi-site admin controllers.
 *
 * Hits the running OpenEMR HTTP endpoint (default http://localhost or
 * OPENEMR_BASE_URL / OPENEMR_BASE_URL_ADMIN). Skips when the endpoint is unreachable.
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Controllers\Admin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminHttpIntegrationTest extends TestCase
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private string $cookieJar = '';

    protected function setUp(): void
    {
        parent::setUp();
        $base = getenv('OPENEMR_BASE_URL_ADMIN')
            ?: getenv('OPENEMR_BASE_URL')
            ?: 'http://localhost';
        $this->baseUrl = rtrim((string) $base, '/');
        $this->username = getenv('OE_USER') ?: 'admin';
        $this->password = getenv('OE_PASS') ?: 'pass';
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'oe-admin-cj-') ?: '';

        if (!$this->isEndpointReachable()) {
            $this->markTestSkipped('Admin HTTP endpoint not reachable at ' . $this->baseUrl);
        }
    }

    protected function tearDown(): void
    {
        if ($this->cookieJar !== '' && is_file($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
        parent::tearDown();
    }

    private function isEndpointReachable(): bool
    {
        $resp = $this->request('GET', '/admin/login.php');
        return $resp['http_code'] > 0 && $resp['http_code'] < 500;
    }

    /**
     * @return array{http_code: int, headers: string, body: string, headers_map: array<string, string>}
     */
    private function request(string $method, string $path, array $postFields = [], bool $follow = false): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['http_code' => 0, 'headers' => '', 'body' => '', 'headers_map' => []];
        }

        $headers = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => $follow ? 5 : 0,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'OpenEMR-AdminHttp-IntegrationTest/1.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // Do not call curl_close(): deprecated in PHP 8.5 and a no-op since 8.0.

        if (!is_string($raw)) {
            return ['http_code' => 0, 'headers' => '', 'body' => '', 'headers_map' => []];
        }

        $hdr = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $map = [];
        foreach (explode("\r\n", $hdr) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $map[strtolower(trim($k))] = trim($v);
            }
        }

        return [
            'http_code' => $code,
            'headers' => $hdr,
            'body' => $body,
            'headers_map' => $map,
        ];
    }

    private function extractCsrf(string $html): string
    {
        if (preg_match('/name=["\']csrf_token_form["\'][^>]*value=["\']([^"\']+)["\']/', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']csrf_token_form["\']/', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    #[Test]
    public function loginGetReturnsFormWithCsrf(): void
    {
        $resp = $this->request('GET', '/admin/login.php');
        $this->assertSame(200, $resp['http_code']);
        $this->assertStringContainsString('csrf_token_form', $resp['body']);
        $this->assertStringContainsString('authUser', $resp['body']);
        $this->assertStringContainsString('Multi-Site', $resp['body']);
        // no side-nav shell on login
        $this->assertStringNotContainsString('id="adminShell"', $resp['body']);
        $this->assertNotSame('', $this->extractCsrf($resp['body']));
    }

    #[Test]
    public function indexRedirectsUnauthenticatedToLogin(): void
    {
        // fresh jar
        if (is_file($this->cookieJar)) {
            unlink($this->cookieJar);
            touch($this->cookieJar);
        }
        $resp = $this->request('GET', '/admin/index.php');
        $this->assertContains($resp['http_code'], [302, 303]);
        $loc = $resp['headers_map']['location'] ?? '';
        $this->assertStringContainsString('login.php', $loc);
    }

    #[Test]
    public function loginPostWithBadCsrfReturns403(): void
    {
        $this->request('GET', '/admin/login.php'); // establish session
        $resp = $this->request('POST', '/admin/login.php', [
            'csrf_token_form' => 'definitely-invalid-token',
            'authUser' => $this->username,
            'clearPass' => $this->password,
            'login_submit' => '1',
        ]);
        $this->assertSame(403, $resp['http_code']);
    }

    #[Test]
    public function loginPostWithoutRotatingCsrfSucceedsAndDashboardRenders(): void
    {
        $get = $this->request('GET', '/admin/login.php');
        $this->assertSame(200, $get['http_code']);
        $csrf = $this->extractCsrf($get['body']);
        $this->assertNotSame('', $csrf);

        $post = $this->request('POST', '/admin/login.php', [
            'csrf_token_form' => $csrf,
            'authUser' => $this->username,
            'clearPass' => $this->password,
            'login_submit' => '1',
        ]);
        $this->assertContains($post['http_code'], [302, 303], $post['body']);
        $this->assertStringContainsString('index.php', $post['headers_map']['location'] ?? '');

        $dash = $this->request('GET', '/admin/index.php');
        $this->assertSame(200, $dash['http_code'], substr($dash['body'], 0, 500));
        $this->assertStringContainsString('id="adminShell"', $dash['body']);
        $this->assertStringContainsString('Configured Sites', $dash['body']);
        $this->assertStringContainsString('fa-check-circle', $dash['body']);
        $this->assertStringContainsString('fontawesome', strtolower($dash['body']));
    }

    #[Test]
    public function dashboardLogoutRequiresPostCsrf(): void
    {
        // login first
        $get = $this->request('GET', '/admin/login.php');
        $csrf = $this->extractCsrf($get['body']);
        $this->request('POST', '/admin/login.php', [
            'csrf_token_form' => $csrf,
            'authUser' => $this->username,
            'clearPass' => $this->password,
            'login_submit' => '1',
        ]);

        $dash = $this->request('GET', '/admin/index.php');
        $this->assertSame(200, $dash['http_code']);
        $logoutCsrf = $this->extractCsrf($dash['body']);
        $this->assertNotSame('', $logoutCsrf);

        // GET logout rejected
        $getLogout = $this->request('GET', '/admin/index.php?logout=1');
        $this->assertSame(405, $getLogout['http_code']);

        // POST logout succeeds
        $postLogout = $this->request('POST', '/admin/index.php', [
            'csrf_token_form' => $logoutCsrf,
            'admin_action' => 'logout',
            'logout' => '1',
        ]);
        $this->assertContains($postLogout['http_code'], [302, 303]);
        $this->assertStringContainsString('login.php', $postLogout['headers_map']['location'] ?? '');

        // session cleared
        $after = $this->request('GET', '/admin/index.php');
        $this->assertContains($after['http_code'], [302, 303]);
    }

    #[Test]
    public function rootAdminPhpRedirectsToLogin(): void
    {
        $resp = $this->request('GET', '/admin.php');
        $this->assertContains($resp['http_code'], [302, 303]);
        $this->assertStringContainsString('admin/login.php', $resp['headers_map']['location'] ?? '');
    }
}
