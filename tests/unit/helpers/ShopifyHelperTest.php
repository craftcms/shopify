<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\helpers;

use Codeception\Test\Unit;
use craft\shopify\helpers\ShopifyHelper;
use UnitTester;

/**
 * @group helpers
 */
class ShopifyHelperTest extends Unit
{
    public UnitTester $tester;

    // -------------------------------------------------------------------------
    // sanitizeShopDomain
    // -------------------------------------------------------------------------

    public function testSanitizeShopDomainAppendsMyshopifyDomainForBareName(): void
    {
        self::assertEquals('my-store.myshopify.com', ShopifyHelper::sanitizeShopDomain('my-store'));
    }

    public function testSanitizeShopDomainAcceptsFullMyshopifyComDomain(): void
    {
        self::assertEquals('my-store.myshopify.com', ShopifyHelper::sanitizeShopDomain('my-store.myshopify.com'));
    }

    public function testSanitizeShopDomainAcceptsFullMyshopifyIoDomain(): void
    {
        self::assertEquals('my-store.myshopify.io', ShopifyHelper::sanitizeShopDomain('my-store.myshopify.io'));
    }

    public function testSanitizeShopDomainStripsHttpsPrefix(): void
    {
        self::assertEquals('my-store.myshopify.com', ShopifyHelper::sanitizeShopDomain('https://my-store.myshopify.com'));
    }

    public function testSanitizeShopDomainStripsHttpPrefix(): void
    {
        self::assertEquals('my-store.myshopify.com', ShopifyHelper::sanitizeShopDomain('http://my-store.myshopify.com'));
    }

    public function testSanitizeShopDomainLowercasesInput(): void
    {
        self::assertEquals('my-store.myshopify.com', ShopifyHelper::sanitizeShopDomain('My-Store.MyShopify.Com'));
    }

    public function testSanitizeShopDomainTrimsWhitespace(): void
    {
        self::assertEquals('my-store.myshopify.com', ShopifyHelper::sanitizeShopDomain('  my-store.myshopify.com  '));
    }

    public function testSanitizeShopDomainRejectsNonShopifyDomain(): void
    {
        self::assertNull(ShopifyHelper::sanitizeShopDomain('example.com'));
    }

    public function testSanitizeShopDomainRejectsEmptyString(): void
    {
        self::assertNull(ShopifyHelper::sanitizeShopDomain(''));
    }

    public function testSanitizeShopDomainRejectsWhitespaceOnlyString(): void
    {
        self::assertNull(ShopifyHelper::sanitizeShopDomain('   '));
    }

    public function testSanitizeShopDomainRejectsPathTraversalSuffix(): void
    {
        self::assertNull(ShopifyHelper::sanitizeShopDomain('my-store.myshopify.com/../admin'));
    }

    public function testSanitizeShopDomainRejectsQueryStringSuffix(): void
    {
        self::assertNull(ShopifyHelper::sanitizeShopDomain('my-store.myshopify.com?evil=1'));
    }

    public function testSanitizeShopDomainRejectsLeadingHyphen(): void
    {
        self::assertNull(ShopifyHelper::sanitizeShopDomain('-my-store.myshopify.com'));
    }

    public function testSanitizeShopDomainRejectsDotInSubdomainLabel(): void
    {
        // The label before ".myshopify.com" must not itself contain a dot,
        // otherwise a domain-confusion attack like this could smuggle a fake host.
        self::assertNull(ShopifyHelper::sanitizeShopDomain('evil.com.myshopify.com'));
    }

    public function testSanitizeShopDomainRejectsSuffixedLookalikeDomain(): void
    {
        // Must not accept a domain that merely contains "myshopify.com" somewhere.
        self::assertNull(ShopifyHelper::sanitizeShopDomain('myshopify.com.evil.com'));
    }

    public function testSanitizeShopDomainAcceptsSingleCharacterSubdomain(): void
    {
        self::assertEquals('a.myshopify.com', ShopifyHelper::sanitizeShopDomain('a'));
    }

    // -------------------------------------------------------------------------
    // validateHmac
    // -------------------------------------------------------------------------

    public function testValidateHmacReturnsFalseWhenHmacKeyMissing(): void
    {
        self::assertFalse(ShopifyHelper::validateHmac(['shop' => 'my-store.myshopify.com'], 'test_secret_shhh'));
    }

    public function testValidateHmacAcceptsValidSignature(): void
    {
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'timestamp' => '1700000000',
            'code' => 'abc123',
            // Precomputed: hash_hmac('sha256', 'code=abc123&shop=my-shop.myshopify.com&timestamp=1700000000', 'test_secret_shhh')
            'hmac' => 'a681bb12141c571821e74c45d3a83388cbac98af2d498c97c4a782c3af8a31af',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacRejectsTamperedParam(): void
    {
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'timestamp' => '1700000000',
            'code' => 'tampered-code',
            'hmac' => 'a681bb12141c571821e74c45d3a83388cbac98af2d498c97c4a782c3af8a31af',
        ];

        self::assertFalse(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacRejectsWrongSecret(): void
    {
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'timestamp' => '1700000000',
            'code' => 'abc123',
            'hmac' => 'a681bb12141c571821e74c45d3a83388cbac98af2d498c97c4a782c3af8a31af',
        ];

        self::assertFalse(ShopifyHelper::validateHmac($params, 'a-completely-different-secret'));
    }

    public function testValidateHmacRejectsGarbageHmacValue(): void
    {
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'hmac' => 'not-a-real-signature',
        ];

        self::assertFalse(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacIsOrderIndependent(): void
    {
        // Params are sorted internally, so submitting them in a different key order
        // should still validate against the same signature.
        $params = [
            'timestamp' => '1700000000',
            'hmac' => 'a681bb12141c571821e74c45d3a83388cbac98af2d498c97c4a782c3af8a31af',
            'code' => 'abc123',
            'shop' => 'my-shop.myshopify.com',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacHandlesArrayValuedParams(): void
    {
        $params = [
            'ids' => ['1', '2'],
            'shop' => 'my-shop.myshopify.com',
            // Precomputed: hash_hmac('sha256', 'ids=%5B%221%22%2C%222%22%5D&shop=my-shop.myshopify.com', 'test_secret_shhh')
            'hmac' => '89ef6f86296cd4a979fc7c17d7d6f915a89aa2216cf430723a5f9ca131435285',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacAcceptsSignatureOfEmptyParamSet(): void
    {
        // Edge case: with no params besides `hmac`, the signed query string is empty.
        $params = [
            // Precomputed: hash_hmac('sha256', '', 'test_secret_shhh')
            'hmac' => '82783bf294739b92ae7e7be2ff20a30ec2cd80cb8a2536916644268572182039',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    // -------------------------------------------------------------------------
    // validateHmac: encoding / canonicalization edge cases
    // -------------------------------------------------------------------------

    public function testValidateHmacEncodesSpacesAsPlusSign(): void
    {
        // urlencode() (not rawurlencode()) must be used, so spaces become `+`, matching
        // Shopify's own canonical form. Using `%20` here would fail to validate.
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'state' => 'foo bar',
            // Precomputed: hash_hmac('sha256', 'shop=my-shop.myshopify.com&state=foo+bar', 'test_secret_shhh')
            'hmac' => '218d30f535682d6c49b473bcaef52bba0748f6d55645cbb470b36d0991521615',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacPercentEncodesAmpersandAndEqualsInValues(): void
    {
        // A value containing `&` or `=` must be percent-encoded so it can't be mistaken
        // for an additional parameter or key/value separator.
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'state' => 'a&b=c',
            // Precomputed: hash_hmac('sha256', 'shop=my-shop.myshopify.com&state=a%26b%3Dc', 'test_secret_shhh')
            'hmac' => '8b3daf029726ca3702e44cfc269b32e306ce678d20b806319ac3375e91ff08e5',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacPercentEncodesLiteralPercentSign(): void
    {
        // A literal `%` in a value must itself be escaped (to `%25`), or it would be
        // misread as the start of a percent-encoded sequence.
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'state' => '100%',
            // Precomputed: hash_hmac('sha256', 'shop=my-shop.myshopify.com&state=100%25', 'test_secret_shhh')
            'hmac' => '40d8975574763e4a0b04b8b0bf355f6346818e2c136a902487188abd37516975',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacPercentEncodesMultibyteUnicodeValue(): void
    {
        // Multibyte UTF-8 values must be percent-encoded byte-for-byte.
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'state' => 'café',
            // Precomputed: hash_hmac('sha256', 'shop=my-shop.myshopify.com&state=caf%C3%A9', 'test_secret_shhh')
            'hmac' => '4e447001ed0870f3526076eb709599631f3b79b0c5fe72b1c3de99eda821bc0b',
        ];

        self::assertTrue(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }

    public function testValidateHmacRejectsMismatchedEncodingOfSpaces(): void
    {
        // A signature computed against `%20`-encoded spaces (RFC 3986) rather than `+`
        // (RFC 1738 / urlencode) must NOT validate.
        $params = [
            'shop' => 'my-shop.myshopify.com',
            'state' => 'foo bar',
            // Precomputed: hash_hmac('sha256', 'shop=my-shop.myshopify.com&state=foo%20bar', 'test_secret_shhh')
            'hmac' => 'e466c63f23328c630405d39eb7b0840046472e586d3aea536324a70d878430ed',
        ];

        self::assertFalse(ShopifyHelper::validateHmac($params, 'test_secret_shhh'));
    }
}
