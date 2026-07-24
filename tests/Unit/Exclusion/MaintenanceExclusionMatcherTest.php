<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Exclusion;

use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MaintenanceExclusionMatcherTest extends TestCase
{
    public function testExactPath(): void
    {
        $matcher = new MaintenanceExclusionMatcher(paths: ['/health']);
        self::assertTrue($matcher->matches(Request::create('/health')));
        self::assertFalse($matcher->matches(Request::create('/other')));
    }

    public function testPathPrefix(): void
    {
        $matcher = new MaintenanceExclusionMatcher(pathPrefixes: ['/_maintenance']);
        self::assertTrue($matcher->matches(Request::create('/_maintenance/login')));
        self::assertFalse($matcher->matches(Request::create('/site')));
    }

    public function testRouteName(): void
    {
        $matcher = new MaintenanceExclusionMatcher(routes: ['app_health']);
        $request = Request::create('/x');
        $request->attributes->set('_route', 'app_health');
        self::assertTrue($matcher->matches($request));
    }

    public function testGlobAndRegexPatterns(): void
    {
        $matcher = new MaintenanceExclusionMatcher(patterns: ['/_profiler*', '#^/api/health$#']);
        self::assertTrue($matcher->matches(Request::create('/_profiler/123')));
        self::assertTrue($matcher->matches(Request::create('/api/health')));
        self::assertFalse($matcher->matches(Request::create('/api/users')));
    }

    public function testTildeRegexPattern(): void
    {
        $matcher = new MaintenanceExclusionMatcher(patterns: ['~^/metrics$~']);
        self::assertTrue($matcher->matches(Request::create('/metrics')));
    }

    public function testEmptyPrefixAndPatternAreIgnored(): void
    {
        $matcher = new MaintenanceExclusionMatcher(pathPrefixes: [''], patterns: ['']);
        self::assertFalse($matcher->matches(Request::create('/anything')));
    }
}
