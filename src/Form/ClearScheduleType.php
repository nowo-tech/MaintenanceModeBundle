<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * CSRF-only form to clear scheduled enable / disable timestamps.
 */
final class ClearScheduleType extends AbstractMaintenanceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('confirmed', HiddenType::class, [
            'data' => '1',
        ]);
    }

    protected function csrfTokenId(): string
    {
        return MaintenancePanelController::CSRF_CLEAR_SCHEDULE;
    }
}
