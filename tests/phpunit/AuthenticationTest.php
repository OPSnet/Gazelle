<?php

namespace phpunit;

use Gazelle\Authentication;
use Gazelle\Base;
use Gazelle\Enum\AuthnMechanism;
use Gazelle\Enum\UserStatus;
use Gazelle\Exception\AuthnError;
use Gazelle\LoginWatch;
use Gazelle\Manager\IPv4;
use Gazelle\RequestContext;
use Gazelle\SessionCookie;
use Gazelle\User;
use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class AuthenticationTest extends TestCase {
    private const IP = '127.128.129.130';
    protected User $user;
    protected LoginWatch $watch;
    protected \Gazelle\Manager\Ban $banMan;

    public function setUp(): void {
        Base::setRequestContext(new RequestContext('/user.php', self::IP, ''));
    }

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->pg()->query("delete from login_watch where ip = $1", self::IP);
            $this->user->remove();
            unset($this->user);
        }
        if (isset($this->banMan)) {
            $this->banMan->pg()->query("delete from ip_ban where ip = $1", self::IP);
            unset($this->banMan);
        }
        unset($this->watch, $_SERVER['HTTP_AUTHORIZATION'], $_COOKIE['session']);
    }

    public function testAuthorizationHeaderInvalid(): void {
        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid token/');
        new Authentication()->findByAuthorization('invalid');
    }

    public function testAuthorizationHeaderInvalid2(): void {
        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid token/');
        new Authentication()->findByAuthorization('token invalid');
    }

    public function testAuthorizationHeaderInvalid3(): void {
        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid authorization /');
        new Authentication()->findByAuthorization('basic invalid');
    }

    public function testAuthorizationHeaderInvalid4(): void {
        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid authorization /');
        new Authentication()->findByAuthorization('token token token');
    }

    public function testAuthorizationHeaderValid(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $token = $this->user->createApiToken('phpunit.authn');
        $authn = new Authentication();

        $u = $authn->findByAuthorization('token ' . $token);
        $this->assertSame($this->user->id, $u->id, 'authn-token-valid-user');
        $this->assertSame('phpunit.authn', $authn->data(), 'authn-token-valid-token');

        $u = $authn->findByAuthorization($token);
        $this->assertSame($this->user->id, $u->id, 'authn-token-valid-user2');
        $this->assertSame('phpunit.authn', $authn->data(), 'authn-token-valid-compat-token');

        Base::setRequestContext(new RequestContext('/ajax.php', self::IP, ''));
        $_SERVER['HTTP_AUTHORIZATION'] = 'token ' . $token;
        $this->assertSame($this->user->id, $authn->tryAuthenticate()->id, 'authn-token-try-authenticate');
        $this->assertSame(AuthnMechanism::apikey, $authn->mechanism(), 'authn-token-mechanism');
        $this->assertSame($this->user->id, Base::staticRequestContext()->viewer()->id, 'authn-token-viewer');

        $this->user->setField('lock-type', 1)->modify();
        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid token/');
        $authn->findByAuthorization('token ' . $token);
    }

    public function testAuthorizationHeaderRevokedToken(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $token = $this->user->createApiToken('phpunit.authn');
        $tokenList = $this->user->apiTokenList();
        $this->assertSame(1, count($tokenList), 'authn-token-revoked-tokenlist');
        $this->user->revokeApiTokenById($tokenList[0]['id']);
        $this->assertSame(0, count($this->user->apiTokenList()), 'authn-token-revoked-tokenlist-empty');
        $authn = new Authentication();

        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid token/');
        $authn->findByAuthorization('token ' . $token);
    }

    public function testAuthorizationHeaderDisabled(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn');
        $token = $this->user->createApiToken('phpunit.authn');
        $this->user->setField('Enabled', UserStatus::disabled->value)->modify();

        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/^invalid token/');
        new Authentication()->findByAuthorization('token ' . $token);
    }

    public function testAuthorizationHeaderBan(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn');
        $token = $this->user->createApiToken('phpunit.authn');
        $this->user->setField('Enabled', UserStatus::disabled->value)->modify();

        for ($i = 0; $i <= 10; $i++) {
            $sawException = false;
            try {
                new Authentication()->findByAuthorization('token ' . $token);
            } catch (AuthnError) {
                $sawException = true;
            }
            $this->assertTrue($sawException, 'authn-token-ban-exception');
        }

        $this->watch = new LoginWatch(self::IP);
        $this->assertSame(11, $this->watch->attemptTotal(), 'authn-token-ban-inc-attempts');
        $this->assertNotNull($this->watch->blockedUntil(), 'authn-token-ban-blocked');
    }

    public function testFindByCookieInvalid(): void {
        $cookie = new SessionCookie('');
        $this->expectException(AuthnError::class);
        new Authentication()->findByCookie($cookie);
    }

    public function testFindByCookieDeletedUser(): void {
        $sessionKey = randomString(10);
        $user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $cookie = new SessionCookie(SessionCookie::encode($user, $sessionKey));
        $user->remove();
        $this->expectException(AuthnError::class);
        new Authentication()->findByCookie($cookie);
    }

    public function testFindByCookieInvalidSession(): void {
        $sessionKey = randomString(10);
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $cookie = new SessionCookie(SessionCookie::encode($this->user, $sessionKey));
        $this->expectException(AuthnError::class);
        new Authentication()->findByCookie($cookie);
    }

    public function testFindByCookieDisabledUser(): void {
        $sessionKey = randomString(10);
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn');
        $cookie = new SessionCookie(SessionCookie::encode($this->user, $sessionKey));
        $this->user->setField('Enabled', UserStatus::disabled->value)->modify();
        $this->expectException(AuthnError::class);
        new Authentication()->findByCookie($cookie);
    }

    public function testFindByCookieDisabledUserAllowed(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn');
        $ctx = $this->user->requestContext();
        $sessionKey = $this->user->session()->create([
            'keep-logged' => '1',
            'browser'     => $ctx->ua(),
            'ipaddr'      => $ctx->remoteAddr(),
            'useragent'   => $ctx->useragent(),
        ])['SessionID'];
        $cookie = new SessionCookie(SessionCookie::encode($this->user, $sessionKey));
        $this->user->setField('Enabled', UserStatus::disabled->value)->modify();
        $ctx = new RequestContext('/login.php', self::IP, '');
        Base::setRequestContext($ctx);
        $authn = $ctx->authn;
        $this->assertSame($this->user->id, $authn->findByCookie($cookie)->id, 'authn-cookie-disabled-allowed-user');
        $this->assertSame($sessionKey, $ctx->sessionId(), 'authn-cookie-disabled-allowed-sessionid');

        $_COOKIE['session'] = $cookie->payload();
        $this->assertSame($this->user->id, $authn->tryAuthenticate()->id, 'authn-cookie-disabled-allowed-try-authenticate');
        $this->assertSame(AuthnMechanism::session, $authn->mechanism(), 'authn-cookie-disabled-allowed-mechanism');
        $this->assertSame($this->user->id, Base::staticRequestContext()->viewer()->id, 'authn-cookie-disabled-allowed-viewer');
    }

    public function testFindByCookieSuccess(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $ctx = $this->user->requestContext();
        $sessionKey = $this->user->session()->create([
            'keep-logged' => '1',
            'browser'     => $ctx->ua(),
            'ipaddr'      => $ctx->remoteAddr(),
            'useragent'   => $ctx->useragent(),
        ])['SessionID'];
        $cookie = new SessionCookie(SessionCookie::encode($this->user, $sessionKey));
        $authn = $ctx->authn;
        $this->assertSame($this->user->id, $authn->findByCookie($cookie)->id, 'authn-cookie-valid-user');
        $this->assertSame($sessionKey, Base::staticRequestContext()->sessionId(), 'authn-cookie-valid-sessionid');

        $_COOKIE['session'] = $cookie->payload();
        $this->assertSame($this->user->id, $authn->tryAuthenticate()->id, 'authn-cookie-try-authenticate');
        $this->assertSame(AuthnMechanism::session, $authn->mechanism(), 'authn-cookie-mechanism');
        $this->assertSame($this->user->id, Base::staticRequestContext()->viewer()->id, 'authn-cookie-viewer');
        $this->assertSame($sessionKey, $authn->data(), 'authn-cookie-data');
        $this->assertSame($sessionKey, $authn->requestContext()->sessionId(), 'authn-cookie-sessionid');
    }

    public function testAuthenticateBanned(): void {
        Base::setRequestContext(new RequestContext('/index.php', self::IP, ''));
        $this->banMan = new \Gazelle\Manager\Ban();
        $this->banMan->create(self::IP, 'phpunit.authn');
        $this->expectException(AuthnError::class);
        $this->expectExceptionMessageMatches('/banned/');
        new Authentication()->tryAuthenticate();
    }

    public function testAuthenticatePasskeyValid(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $_REQUEST['action'] = $_GET['action'] = 'download';
        $_REQUEST['torrendid'] = $_GET['id'] = '123';
        $_REQUEST['torrent_pass'] = $_GET['torrent_pass'] = $this->user->announceKey();
        $ctx = new RequestContext('/torrents.php', self::IP, '');
        Base::setRequestContext($ctx);
        $authn = $ctx->authn;
        $this->assertSame($this->user->id, $authn->tryAuthenticate()->id, 'authn-passkey-try-authenticate');
        $this->assertSame(AuthnMechanism::passkey, $authn->mechanism(), 'authn-passkey-mechanism');
        $this->assertSame($this->user->id, Base::staticRequestContext()->viewer()->id, 'authn-passkey-viewer');
        $this->assertSame($this->user->announceKey(), $authn->data(), 'authn-passkey-data');
    }

    public function testAuthenticatePasskeyInvalid(): void {
        $_REQUEST['action'] = $_GET['action'] = 'download';
        $_REQUEST['torrent_pass'] = $_GET['torrent_pass'] = 'invalid';
        $ctx = new RequestContext('/torrents.php', self::IP, '');
        Base::setRequestContext($ctx);
        $this->expectException(AuthnError::class);
        $ctx->authn->tryAuthenticate();
    }

    public function testAuthenticatePasskeyDisabled(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn');
        $_REQUEST['action'] = $_GET['action'] = 'download';
        $_REQUEST['id'] = $_GET['id'] = '123';
        $_REQUEST['torrent_pass'] = $_GET['torrent_pass'] = $this->user->announceKey();
        $ctx = new RequestContext('/torrents.php', self::IP, '');
        Base::setRequestContext($ctx);
        $this->user->setField('Enabled', UserStatus::disabled->value)->modify();
        $this->expectException(AuthnError::class);
        $ctx->authn->tryAuthenticate();
    }

    public function testAuthenticatePasskeyLocked(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $_REQUEST['action'] = $_GET['action'] = 'download';
        $_REQUEST['id'] = $_GET['id'] = '123';
        $_REQUEST['torrent_pass'] = $_GET['torrent_pass'] = $this->user->announceKey();
        $ctx = new RequestContext('/torrents.php', self::IP, '');
        Base::setRequestContext($ctx);
        $this->user->setField('lock-type', 1)->modify();
        $this->expectException(AuthnError::class);
        $ctx->authn->tryAuthenticate();
    }

    public function testAuthenticatePublic(): void {
        $ctx = new RequestContext('/register.php', self::IP, '');
        Base::setRequestContext($ctx);
        $authn = $ctx->authn;
        $this->assertNull($authn->tryAuthenticate());
        $this->assertSame(AuthnMechanism::none, $authn->mechanism(), 'authn-public-mechanism');
        $this->assertNull($authn->data(), 'authn-public-data');
    }

    public function testAuthenticateNonPublic(): void {
        $this->expectException(AuthnError::class);
        new Authentication()->tryAuthenticate();
    }

    public function testAuthenticateOcelotInvalid(): void {
        $_REQUEST['action'] = $_GET['action'] = 'ocelot';
        $_REQUEST['key'] = $_GET['key'] = 'invalid';
        $ctx = new RequestContext('/tools.php', self::IP, '');
        Base::setRequestContext($ctx);
        $this->expectException(AuthnError::class);
        $ctx->authn->tryAuthenticate();
    }

    public function testAuthenticateOcelotValid(): void {
        $_REQUEST['action'] = $_GET['action'] = 'ocelot';
        $_REQUEST['key'] = $_GET['key'] = TRACKER_SECRET;
        $ctx = new RequestContext('/tools.php', self::IP, '');
        Base::setRequestContext($ctx);
        $authn = $ctx->authn;
        $this->assertNull($authn->tryAuthenticate());
        $this->assertSame(AuthnMechanism::none, $authn->mechanism(), 'authn-ocelot-mechanism');
        $this->assertSame('ocelot', $authn->data(), 'authn-ocelot-data');
    }

    public function testUpdateIpApi(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $_REQUEST['action'] = $_GET['action'] = 'download';
        $_REQUEST['id'] = $_GET['id'] = '123';
        $_REQUEST['torrent_pass'] = $_GET['torrent_pass'] = $this->user->announceKey();
        $ctx = new RequestContext('/torrents.php', self::IP, '');
        Base::setRequestContext($ctx);
        $authn = $ctx->authn;
        $authn->tryAuthenticate();

        // sanity
        $cnt = $authn->pg()->scalarex("select count(*) from ip_history where data_origin = 'token' and id_user = $1", $this->user->id);
        $this->assertSame(0, $cnt, 'authn-update-ip-api-initial');

        $authn->updateIp(null);  // noop
        $authn->updateIp($this->user);
        $rows = $authn->pg()->allex("select ip from ip_history where data_origin = 'token' and id_user = $1", $this->user->id);
        $this->assertCount(1, $rows, 'authn-update-ip-api-rows');
        $this->assertSame(self::IP, $rows[0]['ip'], 'authn-update-ip-api-ip');
        $this->assertSame(self::IP, $ctx->remoteAddr(), 'authn-update-ip-api-remoteaddr');
    }

    public function testUpdateIpApiProtected(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $this->user->modifyPrivilegeList(['site_disable_ip_history' => true]);
        $_REQUEST['action'] = $_GET['action'] = 'download';
        $_REQUEST['id'] = $_GET['id'] = '123';
        $_REQUEST['torrent_pass'] = $_GET['torrent_pass'] = $this->user->announceKey();
        $ctx = new RequestContext('/torrents.php', self::IP, '');
        Base::setRequestContext($ctx);
        $authn = $ctx->authn;
        $authn->tryAuthenticate();
        $this->assertSame(AuthnMechanism::passkey, $authn->mechanism(), 'authn-protected-mechanism');

        // sanity
        $cnt = $authn->pg()->scalarex("select count(*) from ip_history where data_origin = 'token' and id_user = $1", $this->user->id);
        $this->assertSame(0, $cnt, 'authn-update-ip-api-protected-initial');

        $authn->updateIp($this->user);
        $rows = $authn->pg()->allex("select ip from ip_history where data_origin = 'token' and id_user = $1", $this->user->id);
        $this->assertCount(1, $rows, 'authn-update-ip-api-rows');
        $this->assertSame('127.0.0.1', $rows[0]['ip'], 'authn-update-ip-api-protected-ip');
        $this->assertSame('127.0.0.1', $ctx->remoteAddr(), 'authn-update-ip-api-protected-remoteaddr');
    }

    public function testUpdateIpSession(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $ctx = $this->user->requestContext();
        $authn = $ctx->authn;

        $sessionKey = $this->user->session()->create([
            'keep-logged' => '1',
            'browser'     => $ctx->ua(),
            'ipaddr'      => $ctx->remoteAddr(),
            'useragent'   => $ctx->useragent(),
        ])['SessionID'];
        $_COOKIE['session'] = SessionCookie::encode($this->user, $sessionKey);
        $authn->tryAuthenticate();
        $authn->updateIp(null);  // noop
        $authn->updateIp($this->user);
        $this->assertSame(self::IP, $ctx->remoteAddr(), 'authn-update-ip-session-remoteaddr');
    }

    public function testUpdateIpSessionProtected(): void {
        $this->user = Helper::makeUser('authn.' . randomString(10), 'authn', true);
        $this->user->modifyPrivilegeList(['site_disable_ip_history' => true]);
        $ctx = $this->user->requestContext();
        $authn = $ctx->authn;

        $sessionKey = $this->user->session()->create([
            'keep-logged' => '1',
            'browser'     => $ctx->ua(),
            'ipaddr'      => $ctx->remoteAddr(),
            'useragent'   => $ctx->useragent(),
        ])['SessionID'];
        $_COOKIE['session'] = SessionCookie::encode($this->user, $sessionKey);
        $authn->tryAuthenticate();
        $authn->updateIp($this->user);
        $this->assertSame('127.0.0.1', $ctx->remoteAddr(), 'authn-update-ip-session-protected-remoteaddr');
    }
}
