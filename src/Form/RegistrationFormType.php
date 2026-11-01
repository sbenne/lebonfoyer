<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\CedantStatus;
use App\Enum\HousingType;
use App\Enum\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'vous@exemple.fr'],
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone (facultatif)',
                'required' => false,
                'constraints' => [new Assert\Length(max: 30)],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 12, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => UserType::class,
                'label' => 'Je suis…',
                'choices' => [UserType::Adoptant, UserType::Cedant],
                'choice_label' => static fn (UserType $type): string => match ($type) {
                    UserType::Adoptant => 'À la recherche d\'un animal (adoptant)',
                    UserType::Cedant => 'Je propose un animal à l\'adoption (cédant)',
                    default => $type->label(),
                },
                'expanded' => true,
            ])
            ->add('cedantStatus', EnumType::class, [
                'class' => CedantStatus::class,
                'label' => 'Mon statut de cédant',
                'required' => false,
                'placeholder' => 'Choisir…',
                'choice_label' => static fn (CedantStatus $status): string => $status->label(),
            ])
            ->add('organizationName', TextType::class, [
                'label' => 'Nom de l\'association / élevage',
                'required' => false,
                'constraints' => [new Assert\Length(max: 180)],
            ])
            ->add('siren', TextType::class, [
                'label' => 'SIREN / SIRET',
                'required' => false,
                'constraints' => [new Assert\Length(max: 30)],
            ])
            ->add('rna', TextType::class, [
                'label' => 'Numéro RNA',
                'required' => false,
                'constraints' => [new Assert\Length(max: 30)],
            ])
            ->add('breedingNumber', TextType::class, [
                'label' => 'Numéro de portée / élevage',
                'required' => false,
                'constraints' => [new Assert\Length(max: 60)],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'constraints' => [new Assert\Length(max: 120)],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'constraints' => [new Assert\Length(max: 10), new Assert\Regex(pattern: '/^\d{5}$/', message: 'Code postal invalide.')],
            ])
            ->add('housingType', EnumType::class, [
                'class' => HousingType::class,
                'label' => 'Logement',
                'required' => false,
                'placeholder' => 'Choisir…',
                'choice_label' => static fn (HousingType $type): string => $type->label(),
            ])
            ->add('hasGarden', CheckboxType::class, ['label' => 'J\'ai un jardin', 'required' => false])
            ->add('hasTerrace', CheckboxType::class, ['label' => 'J\'ai une terrasse / balcon', 'required' => false])
            ->add('hasOtherAnimals', CheckboxType::class, ['label' => 'J\'ai déjà des animaux', 'required' => false])
            ->add('hasChildren', CheckboxType::class, ['label' => 'J\'ai des enfants', 'required' => false])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les règles de bien-être animal et la politique de confidentialité.',
                'mapped' => false,
                'constraints' => [new Assert\IsTrue(message: 'Vous devez accepter les règles pour continuer.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default', 'registration'],
        ]);
    }
}
