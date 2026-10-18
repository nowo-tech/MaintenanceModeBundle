<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\View;

use Nowo\MaintenanceModeBundle\Tests\Support\PageFixtures;
use Nowo\MaintenanceModeBundle\View\MaintenancePages;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;

final class MaintenancePagesTest extends TestCase
{
    public function testHistoryPaginatesInMemoryAndClampsThePage(): void
    {
        $nav  = PageFixtures::view()->nav('history');
        $page = MaintenancePages::history(PageFixtures::entries(7), 9, 3, $nav);

        self::assertInstanceOf(Pagination::class, $page->pagination);
        self::assertSame(3, $page->pagination->page);
        self::assertSame(3, $page->pagination->totalPages);
        self::assertSame(7, $page->pagination->total);
        self::assertCount(1, $page->rows);

        $first = MaintenancePages::history(PageFixtures::entries(7), -2, 0, $nav);
        self::assertSame(1, $first->pagination?->page);
        self::assertCount(1, $first->rows, 'per page is at least 1');

        self::assertNull(MaintenancePages::history(PageFixtures::entries(2), 1, 25, $nav)->pagination);
    }

    public function testMaintenancePageUsesTheMessageOrTheTranslatedDefault(): void
    {
        self::assertSame('Back at 14:30', MaintenancePages::maintenance('Back at 14:30')->intro);
        $default = MaintenancePages::maintenance('', 'layouts/guest.html.twig');
        self::assertInstanceOf(Label::class, $default->intro);
        self::assertSame('maintenance.page.message', $default->intro->key);
        self::assertSame('layouts/guest.html.twig', $default->layout);
    }

    public function testLoginErrorLabel(): void
    {
        $form = PageFixtures::login()['login_form'];

        self::assertNull(MaintenancePages::login($form)->error);
        self::assertEquals(new Label('panel.login.error', 'NowoMaintenanceModeBundle'), MaintenancePages::login($form, 'Invalid password.')->error);
        self::assertEquals(new Label('panel.login.locked', 'NowoMaintenanceModeBundle'), MaintenancePages::login($form, 'x', true)->error);
    }
}
