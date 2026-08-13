<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Form;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Schedule enable / disable windows and optional message.
 */
final class ScheduleMaintenanceType extends AbstractMaintenanceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scheduled_enable_at', DateTimeType::class, [
                'required' => false,
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'html5'    => true,
                'label'    => 'panel.scheduled_enable',
            ])
            ->add('scheduled_disable_at', DateTimeType::class, [
                'required' => false,
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'html5'    => true,
                'label'    => 'panel.scheduled_disable',
            ])
            ->add('message', TextareaType::class, [
                'required' => false,
                'label'    => 'panel.message',
                'attr'     => ['rows' => 2],
            ]);
    }

    protected function csrfTokenId(): string
    {
        return MaintenancePanelController::CSRF_SCHEDULE;
    }
}
