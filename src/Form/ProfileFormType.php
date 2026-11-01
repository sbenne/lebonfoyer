<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\CedantStatus;
use App\Enum\HousingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $options['data'];

        $builder
            ->add('email', EmailType::class, ['label' => 'Adresse email', 'disabled' => true])
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 80)]])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false, 'constraints' => [new Assert\Length(max: 30)]])
            ->add('city', TextType::class, ['label' => 'Ville', 'required' => false])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'constraints' => [new Assert\Regex(pattern: '/^\d{5}$/', message: 'Code postal invalide.')],
            ]);

        if ($user->isAdoptant()) {
            $builder
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
                ->add('hasChildren', CheckboxType::class, ['label' => 'J\'ai des enfants', 'required' => false]);
        }

        if ($user->isCedant()) {
            $builder
                ->add('cedantStatus', EnumType::class, [
                    'class' => CedantStatus::class,
                    'label' => 'Statut de cédant',
                    'required' => false,
                    'placeholder' => 'Choisir…',
                    'choice_label' => static fn (CedantStatus $status): string => $status->label(),
                ])
                ->add('organizationName', TextType::class, ['label' => 'Nom de l\'association / élevage', 'required' => false])
                ->add('siren', TextType::class, ['label' => 'SIREN / SIRET', 'required' => false])
                ->add('rna', TextType::class, ['label' => 'Numéro RNA', 'required' => false])
                ->add('breedingNumber', TextType::class, ['label' => 'Numéro de portée / élevage', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
