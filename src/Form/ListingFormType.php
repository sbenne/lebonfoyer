<?php

declare(strict_types=1);

namespace App\Form;

use App\Animal\Species;
use App\Entity\AnimalListing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ListingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('species', EnumType::class, [
                'class' => Species::class,
                'label' => 'Espèce',
                'choice_label' => static fn (Species $species): string => Species::Cat === $species ? 'Chat' : 'Chien',
                'expanded' => true,
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'animal',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotNull(message: 'La date de naissance est obligatoire.')],
            ])
            ->add('birthDateEstimated', CheckboxType::class, [
                'label' => 'La date de naissance est estimée',
                'required' => false,
            ])
            ->add('identificationNumber', TextType::class, [
                'label' => 'Numéro d\'identification (puce ou tatouage)',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 8, 'placeholder' => 'Caractère, habitudes, besoins, entente avec les enfants et les autres animaux…'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 40, max: 5000)],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^\d{5}$/', message: 'Code postal invalide.'),
                ],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix (uniquement pour associations et professionnels)',
                'required' => false,
                'currency' => 'EUR',
                'constraints' => [new Assert\PositiveOrZero()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AnimalListing::class,
        ]);
    }
}
