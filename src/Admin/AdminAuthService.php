<?php

/**
 * Admin Authentication Service
 *
 * Authenticates multi-site administration users against the default site only.
 * Enforces Administrators/super ACL on login and on each protected request.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Admin;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Auth\AuthUtils;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Session\SessionUtil;
use OpenEMR\Common\Session\SessionWrapperFactory;

class AdminAuthService
{
    private const DEFAULT_TIMEOUT_MINUTES = 30;

    /**
     * @return array{success: bool, message: string, user_id?: int, username?: string}
     */
    public function authenticate(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Username and password are required',
            ];
        }

        // Multi-site admin always authenticates against the default site (explicit).
        SessionUtil::setSession('site_id', 'default');

        $authUtils = new AuthUtils('login');
        $isValid = $authUtils->confirmPassword($username, $password);

        if (!$isValid) {
            return [
                'success' => false,
                'message' => 'Invalid username or password',
            ];
        }

        $userInfo = $this->getUserInfo($username);
        if ($userInfo === null) {
            return [
                'success' => false,
                'message' => 'User not found or inactive',
            ];
        }

        $userId = (int) $userInfo['id'];
        if (!$this->isAdminUser($userId, $username)) {
            return [
                'success' => false,
                'message' => 'User does not have administrative privileges',
            ];
        }

        return [
            'success' => true,
            'message' => 'Authentication successful',
            'user_id' => $userId,
            'username' => $username,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUserInfo(string $username): ?array
    {
        $result = QueryUtils::querySingleRow(
            'SELECT id, username, fname, lname FROM users WHERE username = ? AND active = 1',
            [$username]
        );

        return is_array($result) && !empty($result['id']) ? $result : null;
    }

    private function isAdminUser(int $userId, string $username): bool
    {
        if (AclMain::aclCheckCore('admin', 'super', $username)) {
            return true;
        }

        $result = QueryUtils::querySingleRow(
            "SELECT COUNT(*) AS count FROM `gacl_groups_aro_map` AS gam
                INNER JOIN `gacl_aro` AS aro ON gam.aro_id = aro.id
                INNER JOIN `gacl_groups` AS g ON gam.group_id = g.id
                WHERE aro.value = ? AND g.value = 'admin'",
            [(string) $userId]
        );

        return ((int) ($result['count'] ?? 0)) > 0;
    }

    public function initializeSession(int $userId, string $username): void
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();

        if (method_exists($session, 'migrate')) {
            $session->migrate(true);
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        SessionUtil::setSession([
            'authUser' => $username,
            'authUserID' => $userId,
            'site_id' => 'default',
            'admin_login' => true,
            'admin_login_time' => time(),
            'admin_login_ip' => $this->clientIp(),
            'admin_login_ua_hash' => $this->userAgentHash(),
        ]);
    }

    public function isAuthenticated(): bool
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();

        $siteId = $session->get('site_id');
        if ($session->get('admin_login') !== true) {
            return false;
        }
        if ($session->get('authUserID') === null || $session->get('authUserID') === '') {
            return false;
        }
        if (!is_string($siteId) || $siteId !== 'default') {
            return false;
        }
        if ($session->get('authUser') === null || $session->get('authUser') === '') {
            return false;
        }

        return true;
    }

    public function revalidateAdminPrivilege(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $username = $session->get('authUser');
        $userId = $session->get('authUserID');
        if (!is_string($username) || $username === '' || $userId === null || $userId === '') {
            return false;
        }

        $storedIp = $session->get('admin_login_ip');
        $storedUa = $session->get('admin_login_ua_hash');
        if (is_string($storedIp) && $storedIp !== '' && $storedIp !== $this->clientIp()) {
            return false;
        }
        if (is_string($storedUa) && $storedUa !== '' && $storedUa !== $this->userAgentHash()) {
            return false;
        }

        return $this->isAdminUser((int) $userId, $username);
    }

    public function logout(): void
    {
        SessionUtil::unsetSession([
            'authUser',
            'authUserID',
            'admin_login',
            'admin_login_time',
            'admin_login_ip',
            'admin_login_ua_hash',
        ]);
    }

    public function checkSessionTimeout(int $timeoutMinutes = self::DEFAULT_TIMEOUT_MINUTES): bool
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $loginTime = $session->get('admin_login_time');
        if ($loginTime === null || $loginTime === '') {
            return false;
        }

        $elapsed = time() - (int) $loginTime;
        if ($elapsed > ($timeoutMinutes * 60)) {
            $this->logout();
            return false;
        }

        SessionUtil::setSession('admin_login_time', time());
        return true;
    }

    public function getUsername(): string
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        $username = $session->get('authUser');
        return is_string($username) ? $username : '';
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? $ip : '';
    }

    private function userAgentHash(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return is_string($ua) && $ua !== '' ? hash('sha256', $ua) : '';
    }
}
