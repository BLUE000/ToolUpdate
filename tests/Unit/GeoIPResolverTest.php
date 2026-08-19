<?php
declare(strict_types=1);

use ReleaseHub\Config\ConfigLoader;
use ReleaseHub\Log\GeoIPResolver;

function runGeoIPResolverTests(): void
{
    $config = new ConfigLoader(TEST_FIXTURES_DIR);
    $resolver = new GeoIPResolver($config->getGeoIpData());

    // UT-02-01: 日本国内IP
    $resJp = $resolver->resolve('123.45.67.89');
    TestAssert::assertEquals('JP', $resJp['country_code'], 'GeoIP: 123.45.67.89 is JP');
    TestAssert::assertEquals('Japan', $resJp['country_name'], 'GeoIP: 123.45.67.89 is Japan');

    // UT-02-02: 米国IP
    $resUs = $resolver->resolve('8.8.8.8');
    TestAssert::assertEquals('US', $resUs['country_code'], 'GeoIP: 8.8.8.8 is US');

    // UT-02-03: ローカルIP
    $resLocal = $resolver->resolve('127.0.0.1');
    TestAssert::assertEquals('LOCAL', $resLocal['country_code'], 'GeoIP: 127.0.0.1 is LOCAL');
    $resPrivate = $resolver->resolve('192.168.1.100');
    TestAssert::assertEquals('LOCAL', $resPrivate['country_code'], 'GeoIP: 192.168.1.100 is LOCAL');

    // UT-02-04: 未定義IP
    $resOther = $resolver->resolve('99.99.99.99');
    TestAssert::assertEquals('OTHER', $resOther['country_code'], 'GeoIP: 99.99.99.99 is OTHER');

    // UT-02-05: 不正形式
    $resInvalid = $resolver->resolve('invalid_ip');
    TestAssert::assertEquals('UNKNOWN', $resInvalid['country_code'], 'GeoIP: invalid_ip is UNKNOWN');
}
