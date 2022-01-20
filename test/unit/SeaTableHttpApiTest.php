<?php

declare(strict_types=1);

/*
 * seatable-api-php
 */

namespace SeaTable\SeaTableApi;

use InterNations\Component\HttpMock\MockBuilder;
use SeaTable\SeaTableApi\Internal\RestCurlClientEx;

/**
 * HttpMockTest
 *
 * @covers \SeaTableAPI
 * @covers \SeaTable\SeaTableApi\SeaTableApi
 * @covers \SeaTable\SeaTableApi\Internal\RestCurlClientEx
 * @covers \SeaTable\SeaTableApi\SeaTableHttpApiTest
 * @covers \SeaTable\SeaTableApi\ServerMockTestCase
 * @uses \SeaTable\SeaTableApi\Internal\ApiOptions
 */
class SeaTableHttpApiTest extends ServerMockTestCase
{
    public function testCreationTriggersAuthTokenRequest()
    {
        $this->mockAuthToken();
        $this->http->setUp();

        new SeaTableApi($this->getOptions());

        self::assertCount(1, $this->http->requests);
        self::assertSame('POST', $this->http->requests->latest()->getMethod());
        self::assertSame('/api2/auth-token/', $this->http->requests->latest()->getPath());
    }

    /**
     * test for default return type and the check account info method
     */
    public function testResponseIsObject()
    {
        $this->mockAuthToken();
        $this->mockAccountInfo();
        $this->http->setUp();

        $actual = (new SeaTableApi($this->getOptions()))->checkAccountInfo();

        self::assertIsObject($actual);
    }

    /**
     * test for $response_object_to_array backwards compat behaviour
     */
    public function testResponseAsArray()
    {
        $this->mockAuthToken();
        $this->mockAccountInfo();
        $this->http->setUp();

        $api = new SeaTableApi($this->getOptions());
        $api->response_object_to_array = true;
        try {
            $actual = $api->checkAccountInfo();
        } catch (\Throwable $t) {
            self::assertSame(E_USER_DEPRECATED, $t->getCode());
            self::assertStringContainsString(' SeaTableApi->response_object_to_array is deprecated ', $t->getMessage());
        }
    }

    /**
     * by default SSL related curl options should be the library default.
     */
    public function testCurlSslDefaultOptions()
    {
        $this->mockAuthToken();
        $this->http->setUp();

        $api = new SeaTableApi($this->getOptions());

        $apiHttpOptions = $this->getInternalHttpOptions($api);
        self::assertArrayNotHasKey(CURLOPT_SSL_VERIFYPEER, $apiHttpOptions);
        self::assertArrayNotHasKey(CURLOPT_SSL_VERIFYHOST, $apiHttpOptions);
    }

    private function getInternalHttpOptions(SeaTableApi $api): array
    {
        $reflectionRecCurlClientEx = new \ReflectionProperty($api, 'restCurlClientEx');
        $reflectionRecCurlClientEx->setAccessible(true);
        /** @var RestCurlClientEx $restCurlClientEx */
        $restCurlClientEx = $reflectionRecCurlClientEx->getValue($api);

        $reflectionHttpOptions = new \ReflectionProperty($restCurlClientEx, 'http_options');
        $reflectionHttpOptions->setAccessible(true);
        return $reflectionHttpOptions->getValue($restCurlClientEx);
    }

    /**
     * (at least) SSL related curl options need to be set via ctor parameter
     * as otherwise there is no upgrade path.
     */
    public function testCurlHttpOptions()
    {
        $this->mockAuthToken();
        $this->http->setUp();

        /** @noinspection CurlSslServerSpoofingInspection */
        $api = new SeaTableApi(
            $this->getOptions(['http_options' => [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]])
        );

        $apiHttpOptions = $this->getInternalHttpOptions($api);
        self::assertArrayHasKey(CURLOPT_SSL_VERIFYPEER, $apiHttpOptions);
        self::assertFalse($apiHttpOptions[CURLOPT_SSL_VERIFYPEER]);
        self::assertArrayHasKey(CURLOPT_SSL_VERIFYHOST, $apiHttpOptions);
        self::assertFalse($apiHttpOptions[CURLOPT_SSL_VERIFYHOST]);
    }

    /**
     * test debug() is not a callable any longer (deprecated in 0.0.4)
     */
    public function testDebugDeprecation()
    {
        $this->mockAuthToken();
        $this->http->setUp();

        $api = new SeaTableApi($this->getOptions());
        self::assertFalse(method_exists($api, 'debug'), 'debug() method must not exist any longer');
        self::assertIsNotCallable([$api, 'debug'], 'SeaTableApi::debug() method must not be callable any longer');
    }

    /**
     * stub initial auth request
     */
    private function mockAuthToken()
    {
        self::assertSame('1', ini_get('zend.assertions'));
        assert(
            $this->http->mock
                ->when()
                ->methodIs('POST')
                ->pathIs('/api2/auth-token/')
                ->then()
                ->body('{"token": null}')
                ->end() instanceof MockBuilder
        );
    }

    /**
     * stub account info request
     */
    private function mockAccountInfo()
    {
        self::assertSame('1', ini_get('zend.assertions'));
        assert(
            $this->http->mock
                ->when()
                ->methodIs('GET')
                ->pathIs('/api2/account/info/')
                ->then()
                ->body('{
  "org_id": 42,
  "is_org_staff": 1,
  "space_usage": "0.0065038%",
  "total": 1000000000,
  "usage": 65038,
  "row_usage_rate": "9.15%",
  "row_total": 2000,
  "row_usage": 183,
  "avatar_url": "https://example.net/image-view/avatars/4/2/39be79e553305e5dcd738fabc9978c/resized/42/306ecbf862d9909f9d87516f32c374fd.png",
  "email": "cb45042f1901a0aeafeb42e464d6582f@auth.local",
  "name": "Jane",
  "login_id": "",
  "contact_email": "jane.doe@example.net",
  "institution": "",
  "is_staff": false,
  "enable_chargebee": false,
  "enable_subscription": false,
  "dtable_updates_email_interval": 0,
  "dtable_collaborate_email_interval": 0
}')
                ->end() instanceof MockBuilder
        );
    }

    /**
     * @return array minimal options for the http mock
     */
    private function getOptions(array $defaults = null): array
    {
        return ((array) $defaults) + [
                'url' => $this->getServerUrl(),
                'user' => 'u',
                'password' => 'p',
            ];
    }
}
