<?php

/**
 * Database-backed integration tests for multi-site AdminAuthService.
 *
 * Requires OpenEMR test bootstrap (interface/globals.php + default site DB).
 * Credentials: OE_USER / OE_PASS (default admin/pass).
 *
 * @package OpenEMR
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Controllers\Admin;

use OpenEMR\Admin\AdminAuthService;
use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Session\SessionWrapperFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminAuthServiceIntegrationTest extends TestCase
{
    private AdminAuthService $auth;

    private string $username;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = new AdminAuthService();
        $this->username = getenv('OE_USER') ?: 'admin';
        $this->password = getenv('OE_PASS') ?: 'pass';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'OpenEMR-AdminAuth-IntegrationTest/1.0';
        SessionUtil::setSession('site_id', 'default');
        $this->auth->logout();
    }

    protected function tearDown(): void
    {
        $this->auth->logout();
        parent::tearDown();
    }

    #[Test]
    public function authenticateRejectsEmptyCredentials(): void
    {
        $result = $this->auth->authenticate('', '');
        $this->assertFalse($result['success']);
        $this->assertSame('Username and password are required', $result['message']);
    }

    #[Test]
    public function authenticateRejectsInvalidPassword(): void
    {
        $result = $this->auth->authenticate($this->username, 'not-the-real-password-xyz');
        $this->assertFalse($result['success']);
        $this->assertSame('Invalid username or password', $result['message']);
    }

    #[Test]
    public function authenticateAcceptsDefaultAdmin(): void
    {
        $result = $this->auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success'], 'Expected admin auth to succeed: ' . json_encode($result));
        $this->assertSame($this->username, $result['username'] ?? null);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertIsInt($result['user_id']);
        $this->assertGreaterThan(0, $result['user_id']);
    }

    #[Test]
    public function sessionLifecycleLoginTimeoutLogout(): void
    {
        $result = $this->auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success']);

        $this->auth->initializeSession((int) $result['user_id'], (string) $result['username']);

        $this->assertTrue($this->auth->isAuthenticated());
        $this->assertTrue($this->auth->checkSessionTimeout());
        $this->assertTrue($this->auth->revalidateAdminPrivilege());
        $this->assertSame($this->username, $this->auth->getUsername());

        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $this->assertSame('default', $session->get('site_id'));
        $this->assertTrue($session->get('admin_login'));

        // Force timeout
        SessionUtil::setSession('admin_login_time', time() - (31 * 60));
        $this->assertFalse($this->auth->checkSessionTimeout(30));
        $this->assertFalse($this->auth->isAuthenticated());
    }

    #[Test]
    public function revalidateFailsOnIpMismatch(): void
    {
        $result = $this->auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success']);
        $this->auth->initializeSession((int) $result['user_id'], (string) $result['username']);
        $this->assertTrue($this->auth->revalidateAdminPrivilege());

        SessionUtil::setSession('admin_login_ip', '10.255.255.1');
        $this->assertFalse($this->auth->revalidateAdminPrivilege());
    }

    #[Test]
    public function revalidateFailsOnUserAgentMismatch(): void
    {
        $result = $this->auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success']);
        $this->auth->initializeSession((int) $result['user_id'], (string) $result['username']);

        SessionUtil::setSession('admin_login_ua_hash', hash('sha256', 'different-ua'));
        $this->assertFalse($this->auth->revalidateAdminPrivilege());
    }

    #[Test]
    public function logoutClearsAdminSessionKeys(): void
    {
        $result = $this->auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success']);
        $this->auth->initializeSession((int) $result['user_id'], (string) $result['username']);
        $this->auth->logout();

        $this->assertFalse($this->auth->isAuthenticated());
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $this->assertNull($session->get('admin_login'));
        $this->assertSame('', $this->auth->getUsername());
    }

    #[Test]
    public function isAuthenticatedRequiresDefaultSite(): void
    {
        $result = $this->auth->authenticate($this->username, $this->password);
        $this->assertTrue($result['success']);
        $this->auth->initializeSession((int) $result['user_id'], (string) $result['username']);
        $this->assertTrue($this->auth->isAuthenticated());

        SessionUtil::setSession('site_id', 'otherclinic');
        $this->assertFalse($this->auth->isAuthenticated());
    }
}
