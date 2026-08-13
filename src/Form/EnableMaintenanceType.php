<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Enable maintenance with an optional public message.
 */
final class EnableMaintenanceType extends AbstractMaintenanceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('message', TextareaType::class, [
            'required' => false,
            'label'    => 'panel.message',
            'attr'     => ['rows' => 3],
        ]);
    }

    protected function csrfTokenId(): string
    {
        return MaintenancePanelController::CSRF_ENABLE;
    }
}
