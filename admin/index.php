<?php

/**
 * Multi Site Administration dashboard (authenticated).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Admin\AdminAuthService;
use OpenEMR\Admin\SiteAdministrationService;
use OpenEMR\Admin\SiteStatusCacheService;
use OpenEMR\Common\Environment\EnvFlag;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\OEGlobalsBag;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/Common/Compatibility/Checker.php';

$response = OpenEMR\Common\Compatibility\Checker::checkPhpVersion();
if ($response !== true) {
    die(htmlspecialchars((string) $response));
}

admin_send_security_headers(true);

$ignoreAuth = true;
$sessionAllowWrite = true;
require_once dirname(__DIR__) . '/interface/globals.php';

/** @var \Symfony\Component\HttpFoundation\Session\SessionInterface $session */
/** @var AdminAuthService $authService */
[$session, $authService] = admin_bootstrap_default_site();
admin_require_auth($authService);

if (isset($_POST['logout']) || (isset($_POST['admin_action']) && $_POST['admin_action'] === 'logout')) {
    admin_require_post_csrf($session, 'admin_dashboard');
    $authService->logout();
    header('Location: login.php', true, 302);
    exit;
}

if (isset($_POST['admin_action']) && $_POST['admin_action'] === 'refresh') {
    admin_require_post_csrf($session, 'admin_dashboard');
    $cacheService = new SiteStatusCacheService();
    $cacheService->invalidateCache();
    header('Location: index.php', true, 302);
    exit;
}

if (isset($_GET['logout']) || (isset($_GET['refresh']) && $_GET['refresh'] === '1')) {
    http_response_code(405);
    header('Allow: POST');
    echo 'This action requires a POST with a valid CSRF token.';
    exit;
}

require_once dirname(__DIR__) . '/version.php';

$webserverRoot = dirname(__DIR__);
if (stripos(PHP_OS, 'WIN') === 0) {
    $webserverRoot = str_replace('\\', '/', $webserverRoot);
}

$oeSitesBase = $webserverRoot . '/sites';
$cacheService = new SiteStatusCacheService();

$sitesInfo = $cacheService->getCachedSitesInfo();
$usingCache = true;
if ($sitesInfo === null) {
    $usingCache = false;
    $service = new SiteAdministrationService($oeSitesBase);
    $sitesInfo = $service->getAllSitesInfo();
    $cacheService->writeCache($sitesInfo);
}

$webroot = OEGlobalsBag::getInstance()->getWebRoot();
$templateVars = [
    'sites' => $sitesInfo,
    'show_add_site_button' => EnvFlag::isEnabled('OPENEMR_ALLOW_MULTISITE_SETUP'),
    'multisite_setup_enabled' => EnvFlag::isEnabled('OPENEMR_ALLOW_MULTISITE_SETUP'),
    'webroot' => $webroot,
    'authenticated' => true,
    'admin_shell' => true,
    'nav_active' => 'sites',
    'username' => $authService->getUsername(),
    'cache_age' => $cacheService->getFormattedCacheAge(),
    'using_cache' => $usingCache,
    'cache_metadata' => $cacheService->getCacheMetadata(),
];

try {
    $kernel = OEGlobalsBag::getInstance()->getKernel();
    $twig = new TwigContainer(null, $kernel);
    echo $twig->getTwig()->render('admin/dashboard.html.twig', $templateVars);
} catch (Throwable $e) {
    error_log('Admin dashboard Twig rendering failed: ' . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><body><h1>Dashboard Error</h1>';
    echo '<p>Unable to render the multi-site dashboard. Check logs.</p>';
    echo '<p><a href="login.php">Back to login</a></p></body></html>';
}
