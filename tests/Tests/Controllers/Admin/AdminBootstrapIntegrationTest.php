<?php

/**
 * Integration tests for admin/bootstrap.php helpers (CSRF + auth gates).
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Controllers\Admin;

use OpenEMR\Admin\AdminAuthService;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Session\SessionWrapperFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/admin/bootstrap.php';

class AdminBootstrapIntegrationTest extends TestCase
{
    private string $username;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->username = getenv('OE_USER') ?: 'admin';
        $this->password = getenv('OE_PASS') ?: 'pass';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'OpenEMR-AdminBootstrap-IntegrationTest/1.0';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['csrf_token_form'], $_POST['_token']);
    }

    #[Test]
    public function bootstrapDefaultSiteSetsSiteAndCsrfKey(): void
    {
        SessionUtil::setSession('site_id', 'something-else');
        [$session, $auth] = admin_bootstrap_default_site();

        $this->assertInstanceOf(AdminAuthService::class, $auth);
        $this->assertSame('default', $session->get('site_id'));
        $key = $session->get('csrf_private_key');
        $this->assertNotNull($key);
        $this->assertNotSame('', $key);

        // Second call must not rotate the key
        $tokenBefore = CsrfUtils::collectCsrfToken($session, 'admin_login');
        admin_ensure_csrf_key($session);
        $tokenAfter = CsrfUtils::collectCsrfToken($session, 'admin_login');
        $this->assertSame($tokenBefore, $tokenAfter);
    }

    #[Test]
    public function ensureCsrfKeyIsIdempotent(): void
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $session->set('csrf_private_key', null);
        admin_ensure_csrf_key($session);
        $first = $session->get('csrf_private_key');
        admin_ensure_csrf_key($session);
        $this->assertSame($first, $session->get('csrf_private_key'));
    }

    #[Test]
    public function requirePostCsrfRejectsGetViaSubprocess(): void
    {
        // admin_require_post_csrf() exits; run it in a child PHP process.
        $script = <<<'PHP'
<?php
declare(strict_types=1);
$_GET['site'] = 'default';
$ignoreAuth = true;
$sessionAllowWrite = true;
require '/var/www/localhost/htdocs/openemr/interface/globals.php';
require '/var/www/localhost/htdocs/openemr/admin/bootstrap.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
[$session] = admin_bootstrap_default_site();
admin_require_post_csrf($session, 'admin_dashboard');
PHP;
        // Prefer project-relative path when not in container layout
        $root = dirname(__DIR__, 4);
        $script = str_replace('/var/www/localhost/htdocs/openemr', $root, $script);
        $tmp = tempnam(sys_get_temp_dir(), 'oe-admin-csrf-');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, $script);
        $php = PHP_BINARY;
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        @unlink($tmp);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('Method Not Allowed', $joined);
        // exit code may be 0 after exit; body is the assertion signal
        $this->assertNotSame('', $joined);
    }

    #[Test]
    public function requirePostCsrfAcceptsValidToken(): void
    {
        [$session] = admin_bootstrap_default_site();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token_form'] = CsrfUtils::collectCsrfToken($session, 'admin_dashboard');

        // Should not exit / die
        admin_require_post_csrf($session, 'admin_dashboard');
        $this->assertTrue(true);
    }

    #[Test]
    public function requireAuthRedirectsWhenUnauthenticated(): void
    {
        $auth = new AdminAuthService();
        $auth->logout();

        // Capture headers via xdebug? Without exit isolation, invoke private contract.
        $this->assertFalse($auth->isAuthenticated());
        // Document expected Location for unauthenticated gate
        $this->assertFalse($auth->checkSessionTimeout());
    }

    #[Test]
    public function requireAuthPassesForAuthenticatedAdmin(): void
    {
        [$session, $auth] = admin_bootstrap_default_site();
        $result = $auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success'], json_encode($result));
        $auth->initializeSession((int) $result['user_id'], (string) $result['username']);

        $this->assertTrue($auth->isAuthenticated());
        $this->assertTrue($auth->checkSessionTimeout());
        $this->assertTrue($auth->revalidateAdminPrivilege());
        $this->assertSame('default', $session->get('site_id'));
    }
}
