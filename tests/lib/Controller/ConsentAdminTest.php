<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\consentadmin\Controller;

use Exception;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module\consent\Auth\Process\Consent;
use SimpleSAML\Module\consentadmin\Controller;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Set of tests for the controllers in the "consentadmin" module.
 *
 * @covers \SimpleSAML\Module\consentadmin\Controller\ConsentAdmin
 */
class ConsentAdminTest extends TestCase
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Session */
    protected Session $session;

    /** @var \SimpleSAML\Auth\Simple */
    protected Auth\Simple $authSimple;

    /** @var \SimpleSAML\Module\consent\Auth\Process\Consent */
    protected Consent $consent;

    /** @var \SimpleSAML\Metadata\MetaDataStorageHandler */
    protected MetaDataStorageHandler $metadataStorageHandler;


    /**
     * Set up for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Configuration::loadFromArray(
            [
                'secretsalt' => 'abc123',
                'module.enable' => [
                    'consent' => true,
                    'consentadmin' => true,
                    'exampleauth' => true,
                ],
            ],
            '[ARRAY]',
            'simplesaml'
        );
        Configuration::setPreLoadedConfig($this->config, 'config.php');

        Configuration::setPreLoadedConfig(
            Configuration::loadFromArray(
                [
                    'authority' => 'exampleauth',
                    'consentadmin'  => [
                        'consent:Database',
                        'dsn'       =>  'mysql:host=DBHOST;dbname=DBNAME',
                        'username'  =>  'USERNAME',
                        'password'  =>  'PASSWORD',
                    ],
                ],
                '[ARRAY]',
                'simplesaml'
            ),
            'module_consentAdmin.php'
        );

        Configuration::setPreLoadedConfig(
            Configuration::loadFromArray(
                [
                    'exampleauth' => [
                        'exampleauth:StaticSource',
                        'eduPersonPrincipalName' => ['testuser@simplesamlphp.org'],
                    ],
                ]
            ),
            'authsources.php'
        );

        $this->session = Session::getSessionFromRequest();

        $this->authSimple = new class ('exampleauth') extends Auth\Simple {
            public function logout($params = null): void
            {
                // stub
            }
            public function requireAuth(array $params = []): void
            {
                // stub
            }
            public function getAttributes(): array
            {
                return ['eduPersonPrincipalName' => 'tester'];
            }
            public function getAuthData(string $name = '')
            {
                if ($name === 'saml:sp:IdP') {
                    return 'localhost';
                }
                return [];
            }
        };

        $this->metadataStorageHandler = new class () extends MetaDataStorageHandler {
            public function __construct()
            {
                // stub
            }
            public function getMetaDataCurrentEntityID(string $set, string $type = 'entityid'): string
            {
                return 'localhost/simplesaml';
            }
            public function getMetaData(?string $entityId, string $set): array
            {
                return ['userid.attribute'];
            }
            public function getList(string $set = 'saml20-idp-hosted', bool $showExpired = false): array
            {
                return [];
            }
        };

        $this->consent = new class () extends Consent {
            public static function getHashedUserID(): string
            {
                return 'abc123@simplesamlphp.org';
            }
        };
    }


    /**
     * Test that accessing the main endpoint with action=true results in a Template
     *
     * @return void
     */
    public function testMainActionTrue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $request = Request::create(
            '/',
            'GET',
            ['action' => 'true', 'cv' => 'urn:some:entity']
        );

        $c = new Controller\ConsentAdmin($this->config, $this->session);
        $c->setAuthSimple($this->authSimple);
        $c->setMetadataStorageHandler($this->metadataStorageHandler);
        $c->setConsent($this->consent);
        $result = $c->main($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Template::class, $result);
    }


    /**
     * Test that accessing the main endpoint with action=false results in a Template
     *
     * @return void
     */
    public function testMainActionFalse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $request = Request::create(
            '/',
            'GET',
            ['action' => 'false', 'cv' => 'urn:some:entity']
        );

        $c = new Controller\ConsentAdmin($this->config, $this->session);
        $c->setAuthSimple($this->authSimple);
        $c->setMetadataStorageHandler($this->metadataStorageHandler);
        $c->setConsent($this->consent);
        $result = $c->main($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Template::class, $result);
    }


    /**
     * Test that accessing the main endpoint with unkown action throws an exception
     *
     * @return void
     */
    public function testMainActionUnknown(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $request = Request::create(
            '/',
            'GET',
            ['action' => 'unknown', 'cv' => 'urn:some:entity']
        );

        $c = new Controller\ConsentAdmin($this->config, $this->session);
        $c->setAuthSimple($this->authSimple);
        $c->setMetadataStorageHandler($this->metadataStorageHandler);
        $c->setConsent($this->consent);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown action (should not happen)');

        $c->main($request);
    }


    /**
     * Test that accessing the main endpoint without action results in a Template
     *
     * @return void
     */
    public function testMainNoAction(): void
    {
        $request = Request::create(
            '/',
            'GET',
            []
        );
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $c = new Controller\ConsentAdmin($this->config, $this->session);
        $c->setAuthSimple($this->authSimple);
        $c->setMetadataStorageHandler($this->metadataStorageHandler);
        $c->setConsent($this->consent);
        $result = $c->main($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Template::class, $result);
    }

}
