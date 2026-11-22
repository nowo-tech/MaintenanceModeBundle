<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * ZA-L1 (1.18.2): POST /message must not write back the copy of the state read before the form was handled. A storage
 * double changes the stored state after the first load, as a disable / schedule edit / tick landing during the request
 * would.
 */
final class MaintenancePanelControllerMessageRaceTest extends TestCase
{
    private InMemoryHistoryStorage $historyStorage;

    protected function setUp(): void
    {
        $this->historyStorage = new InMemoryHistoryStorage();
    }

    public function testDisableBetweenReadAndSaveIsNotUndone(): void
    {
        $enabled  = (new MaintenanceState())->withEnabled(true)->withMessage('Old')->withActivatedAt(new DateTimeImmutable('2026-01-01 10:00:00'));
        $disabled = $enabled->withEnabled(false)->withDeactivatedAt(new DateTimeImmutable('2026-01-01 10:05:00'));
        $storage  = $this->storageChangingAfterFirstLoad($enabled, $disabled);

        $session  = new Session(new MockArraySessionStorage());
        $response = $this->controller($storage)->message($this->request($session, 'New'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($storage->state->isEnabled(), 'maintenance stays off');
        self::assertSame('Old', $storage->state->getMessage());
        self::assertSame(0, $storage->saves, 'nothing is written');
        self::assertSame([], $this->historyStorage->entries, 'no "message" history entry');
        self::assertSame(['panel.flash.message_not_saved'], $session->getFlashBag()->peek('warning'));
        self::assertSame([], $session->getFlashBag()->peek('success'));
    }

    public function testScheduleEditBetweenReadAndSaveIsKept(): void
    {
        $before  = (new MaintenanceState())->withEnabled(true)->withMessage('Old')->withScheduledDisableAt(new DateTimeImmutable('2026-01-01 12:00:00'));
        $after   = $before->withScheduledDisableAt(new DateTimeImmutable('2026-01-01 14:00:00'));
        $storage = $this->storageChangingAfterFirstLoad($before, $after);

        $session = new Session(new MockArraySessionStorage());
        $this->controller($storage)->message($this->request($session, 'Back at 14:00'));

        self::assertTrue($storage->state->isEnabled());
        self::assertSame('Back at 14:00', $storage->state->getMessage());
        self::assertEquals(new DateTimeImmutable('2026-01-01 14:00:00'), $storage->state->getScheduledDisableAt(), 'the newer end is not overwritten');
        self::assertSame(1, $storage->saves);
        self::assertSame('message', $this->historyStorage->entries[0]->getAction());
        self::assertSame(['panel.flash.message_updated'], $session->getFlashBag()->peek('success'));
    }

    public function testEnableBetweenReadAndSaveStoresTheMessage(): void
    {
        $off     = (new MaintenanceState())->withEnabled(false);
        $on      = $off->withEnabled(true)->withMessage('Other operator');
        $storage = $this->storageChangingAfterFirstLoad($off, $on);

        $this->controller($storage)->message($this->request(new Session(new MockArraySessionStorage()), 'Mine'));

        self::assertTrue($storage->state->isEnabled());
        self::assertSame('Mine', $storage->state->getMessage());
    }

    /**
     * @return MaintenanceStateStorageInterface&object{state: MaintenanceState, saves: int}
     */
    private function storageChangingAfterFirstLoad(MaintenanceState $first, MaintenanceState $then): MaintenanceStateStorageInterface
    {
        return new class($first, $then) implements MaintenanceStateStorageInterface {
            public MaintenanceState $state;

            public int $saves = 0;

            private int $loads = 0;

            public function __construct(MaintenanceState $first, private readonly MaintenanceState $then)
            {
                $this->state = $first;
            }

            public function load(): MaintenanceState
            {
                if (++$this->loads === 2) {
                    // Another writer saved between the controller's first read and its write.
                    $this->state = $this->then;
                }

                return $this->state;
            }

            public function save(MaintenanceState $state): void
            {
                ++$this->saves;
                $this->state = $state;
            }
        };
    }

    private function controller(MaintenanceStateStorageInterface $storage): MaintenancePanelController
    {
        $gate = $this->createStub(MaintenanceAccessGateInterface::class);
        $gate->method('isGranted')->willReturn(true);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'valid'));
        $csrf->method('isTokenValid')->willReturn(true);
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($csrf))
            ->getFormFactory();

        return new MaintenancePanelController(
            new MaintenanceManager($storage, $this->historyStorage),
            $gate,
            $formFactory,
            new PanelView($this->createStub(Environment::class)),
            $csrf,
        );
    }

    private function request(Session $session, string $message): Request
    {
        $request = Request::create('/_maintenance/message', 'POST', ['message' => $message, '_token' => 'valid']);
        $request->setSession($session);

        return $request;
    }
}
