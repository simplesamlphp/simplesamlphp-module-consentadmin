<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\consentAdmin\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module\consent\Auth\Process\Consent;
use SimpleSAML\Module\consent\Consent\Store\Database;
use SimpleSAML\Module\consent\Store;
use SimpleSAML\Module\consentAdmin\Controller;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Set of tests for the controllers in the "consentadmin" module.
 */
#[CoversClass(Controller\ConsentAdmin::class)]
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

    /** @var \SimpleSAML\Module\consent\Store */
    protected Store $store;

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
                    'consentAdmin' => true,
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
                    'consentadmin' => [
                        'consent:Database',
                        'dsn' => 'sqlite::memory:',
                    ],
                    'showDescription' => true,
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
            public function getAuthData(string $name = ''): mixed
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
                return ['entityid' => 'localhost/simplesaml', 'metadata-set' => 'saml20-idp-hosted'];
            }
            public function getList(string $set = 'saml20-idp-hosted', bool $showExpired = false): array
            {
                return [];
            }
        };

        $this->consent = new class (['identifyingAttribute' => 'eduPersonPrincipal'], null) extends Consent {
            public static function getHashedUserID(string $userid, string $source): string
            {
                return 'abc123@simplesamlphp.org';
            }
            public static function getTargetedID(string $userid, string $source, string $destination): string
            {
                return hash('sha1', 'abc123@simplesamlphp.org');
            }
        };

        $this->store = new class ([]) extends Store {
            public function __construct(array $config)
            {
                // stub
            }
            public function saveConsent(string $userId, string $destinationId, string $attributeSet): bool
            {
                 return true;
            }
            public function deleteConsent(string $userId, string $destinationId): int
            {
                return 1;
            }
            public function getConsents(string $userId): array
            {
                return [];
            }
            public function hasConsent(string $userId, string $destinationId, string $attributeSet): bool
            {
                return true;
            }
            public static function parseStoreConfig($config): Store
            {
                return new class ($config) extends Database {
                    public function saveConsent(string $userId, string $destinationId, string $attributeSet): bool
                    {
                        return true;
                    }
                    public function deleteConsent(string $userId, string $destinationId): int
                    {
                        return 1;
                    }
                    public function getConsents(string $userId): array
                    {
                        return [];
                    }
                    public function hasConsent(string $userId, string $destinationId, string $attributeSet): bool
                    {
                        return true;
                    }
                };
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
        $c->setStore($this->store);
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
        $c->setStore($this->store);
        $result = $c->main($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Template::class, $result);
    }


    /**
     * Test that accessing the main endpoint with unkown action results in a Template
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
        $result = $c->main($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Template::class, $result);
    }


    /**
     * Test that accessing the main endpoint without action results in a Template
     *
     * @return void
     */
    public function testMainNoAction(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $request = Request::create(
            '/',
            'GET',
            []
        );

        $c = new Controller\ConsentAdmin($this->config, $this->session);
        $c->setAuthSimple($this->authSimple);
        $c->setMetadataStorageHandler($this->metadataStorageHandler);
        $c->setConsent($this->consent);
        $c->setStore($this->store);
        $result = $c->main($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Template::class, $result);
    }
}
