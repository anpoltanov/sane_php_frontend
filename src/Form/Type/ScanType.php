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
        $scanners = $options['scanners'];
        $resolutions = $options['resolutions'];

        $builder
            ->add('deviceName', ChoiceType::class, [
                'label' => 'Scanner',
                'choices' => array_combine($scanners, $scanners) ?: [],
            ])
            ->add('resolution', ChoiceType::class, [
                'label' => 'Resolution (DPI)',
                'choices' => $this->labeledChoices($resolutions),
            ])
            ->add('fileName', TextType::class, [
                'required' => false,
                'label' => 'File name',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Scan',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ScanTask::class,
            'scanners' => [],
            'resolutions' => [],
        ]);
        $resolver->setAllowedTypes('scanners', 'array');
        $resolver->setAllowedTypes('resolutions', 'array');
    }

    /**
     * @param array<int|string> $values
     * @return array<string, int|string>
     */
    private function labeledChoices(array $values): array
    {
        $choices = [];
        foreach ($values as $value) {
            $choices[(string) $value] = is_numeric($value) ? (int) $value : $value;
        }

        return $choices;
    }
}
