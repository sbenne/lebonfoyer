<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AdoptionApplication;
use App\Entity\AnimalListing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ApplicationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var AnimalListing $listing */
        $listing = $options['listing'];

        $builder
            ->add('handoverDate', DateType::class, [
                'label' => 'Date de remise souhaitée',
                'widget' => 'single_text',
                'attr' => ['min' => (new \DateTimeImmutable())->format('Y-m-d')],
                'constraints' => [new Assert\NotNull(message: 'Choisissez une date de remise.')],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message au cédant',
                'required' => false,
                'attr' => ['rows' => 5],
                'constraints' => [new Assert\Length(max: 3000)],
            ]);

        foreach ($listing->getQuestions() as $question) {
            $builder->add('answer_'.$question->getId(), TextareaType::class, [
                'label' => $question->getLabel(),
                'mapped' => false,
                'required' => true,
                'attr' => ['rows' => 3, 'maxlength' => 2000],
                'constraints' => [
                    new Assert\NotBlank(message: 'Répondez à cette question.'),
                    new Assert\Length(max: 2000),
                ],
            ]);
        }

        $builder->add('warningConfirmed', CheckboxType::class, [
            'label' => 'Je comprends que la remise a lieu avant l\'âge recommandé et je confirme ce choix.',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdoptionApplication::class,
        ]);
        $resolver->setRequired('listing');
        $resolver->setAllowedTypes('listing', AnimalListing::class);
    }
}
