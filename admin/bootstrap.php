<?php

/**
 * Shared bootstrap for multi-site admin controllers.
 *
 * Enforces: default site context, CSRF key setup, optional auth gate, POST CSRF.
 *
 * @package OpenEMR
 */

declare(strict_types=1);

use OpenEMR\Admin\AdminAuthService;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Session\SessionWrapperFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Security headers for all admin pages.
 */
function admin_send_security_headers(bool $noStore = false): void
{
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    if ($noStore) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

/**
 * Bootstrap globals against the default site only (explicit, no fallback coalesce).
 *
 * @return array{0: SessionInterface, 1: AdminAuthService}
 */
function admin_bootstrap_default_site(): array
{
    // Callers must set $ignoreAuth / $sessionAllowWrite before requiring globals.php.
    if (!isset($GLOBALS['ignoreAuth'])) {
        // Ensure required flags exist if bootstrap is included after partial setup.
        $ignoreAuth = true;
        $sessionAllowWrite = true;
    }

    SessionUtil::setSession('site_id', 'default');

    $session = SessionWrapperFactory::getInstance()->getActiveSession();
    $siteId = $session->get('site_id');
    if (!is_string($siteId) || $siteId === '' || $siteId !== 'default') {
        // Force default again if something else rewrote it.
        SessionUtil::setSession('site_id', 'default');
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
    }

    CsrfUtils::setupCsrfKey($session);

    return [$session, new AdminAuthService()];
}

/**
 * Require a valid multi-site admin session or redirect to login.
 */
function admin_require_auth(AdminAuthService $authService): void
{
    if (!$authService->isAuthenticated() || !$authService->checkSessionTimeout()) {
        header('Location: login.php', true, 302);
        exit;
    }

    // Re-validate privilege every request (session flag alone is not enough).
    if (!$authService->revalidateAdminPrivilege()) {
        $authService->logout();
        header('Location: login.php?error=privilege', true, 302);
        exit;
    }
}

/**
 * Verify CSRF for state-changing requests. Dies with 403 on failure.
 */
function admin_require_post_csrf(SessionInterface $session, string $subject): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        exit;
    }

    $token = $_POST['csrf_token_form'] ?? $_POST['_token'] ?? '';
    if (!is_string($token) || $token === '' || !CsrfUtils::verifyCsrfToken($token, $session, $subject)) {
        CsrfUtils::csrfNotVerified(true, true);
    }
}
