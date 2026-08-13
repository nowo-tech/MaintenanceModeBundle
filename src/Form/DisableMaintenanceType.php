<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * CSRF-only form to disable maintenance.
 */
final class DisableMaintenanceType extends AbstractMaintenanceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Hidden marker so unnamed forms with only CSRF still submit (HttpFoundation handler).
        $builder->add('confirmed', HiddenType::class, [
            'data' => '1',
        ]);
    }

    protected function csrfTokenId(): string
    {
        return MaintenancePanelController::CSRF_DISABLE;
    }
}
