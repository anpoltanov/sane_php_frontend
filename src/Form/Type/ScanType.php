<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\ScanTask;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fileName', TextType::class, [
                'required' => false,
                'label' => 'File name',
            ])
            ->add('extension', ChoiceType::class, [
                'choices' => ScanTask::getAvailableExtensions(),
                'choice_label' => function ($choice, $key, $value) {
                    return $choice;
                },
            ])
            ->add('resolution', ChoiceType::class, [
                'choices' => ScanTask::getAvailableResolutions(),
                'choice_label' => function ($choice, $key, $value) {
                    return $choice;
                },
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Scan',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ScanTask::class,
        ]);
    }
}