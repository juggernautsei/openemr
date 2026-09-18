<?php

/**
 * Admin Login Controller — multi-site administration.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Admin\AdminAuthService;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\OEGlobalsBag;

require_once __DIR__ . '/bootstrap.php';

admin_send_security_headers(true);

$ignoreAuth = true;
$sessionAllowWrite = true;
require_once dirname(__DIR__) . '/interface/globals.php';

/** @var \Symfony\Component\HttpFoundation\Session\SessionInterface $session */
/** @var AdminAuthService $authService */
[$session, $authService] = admin_bootstrap_default_site();

if ($authService->isAuthenticated() && $authService->checkSessionTimeout() && $authService->revalidateAdminPrivilege()) {
    header('Location: index.php', true, 302);
    exit;
}

$loginFail = false;
$errorMessage = '';
if (isset($_GET['error']) && $_GET['error'] === 'privilege') {
    $loginFail = true;
    $errorMessage = 'Administrative privileges are required';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['login_submit'])) {
    $token = $_POST['csrf_token_form'] ?? $_POST['_token'] ?? '';
    if (!is_string($token) || $token === '' || !CsrfUtils::verifyCsrfToken($token, $session, 'admin_login')) {
        CsrfUtils::csrfNotVerified(true, true);
    }

    $username = is_string($_POST['authUser'] ?? null) ? trim($_POST['authUser']) : '';
    $password = is_string($_POST['clearPass'] ?? null) ? $_POST['clearPass'] : '';

    $result = $authService->authenticate($username, $password);

    if (isset($_POST['clearPass']) && is_string($_POST['clearPass']) && $_POST['clearPass'] !== '') {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($_POST['clearPass']);
        } else {
            $_POST['clearPass'] = '';
        }
    }
    unset($password);

    if ($result['success']) {
        $authService->initializeSession((int) $result['user_id'], (string) $result['username']);
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        CsrfUtils::setupCsrfKey($session);
        header('Location: index.php', true, 302);
        exit;
    }

    $loginFail = true;
    $errorMessage = $result['message'];
    usleep(250000);
}

require_once dirname(__DIR__) . '/version.php';

$viewArgs = [
    'loginFail' => $loginFail,
    'errorMessage' => $errorMessage,
    'version' => ($v_major ?? '') . '.' . ($v_minor ?? '') . '.' . ($v_patch ?? '') . ($v_tag ?? ''),
];

try {
    $kernel = OEGlobalsBag::getInstance()->getKernel();
    $twig = new TwigContainer(null, $kernel);
    echo $twig->getTwig()->render('admin/login.html.twig', $viewArgs);
} catch (Throwable $e) {
    error_log('Admin login Twig rendering failed: ' . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Admin Login Error</title></head><body>';
    echo '<h1>Login Error</h1><p>Unable to load the login page. Please check the system logs.</p>';
    echo '</body></html>';
}
