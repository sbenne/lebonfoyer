<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AnimalListing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ListingQuestionsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var AnimalListing $listing */
        $listing = $options['listing'];
        $existing = [];
        foreach ($listing->getQuestions() as $question) {
            $existing[] = $question->getLabel();
        }

        for ($i = 0; $i < 10; ++$i) {
            $builder->add('question_'.$i, TextType::class, [
                'label' => 'Question '.($i + 1),
                'mapped' => false,
                'required' => false,
                'data' => $existing[$i] ?? null,
                'attr' => ['maxlength' => 300, 'placeholder' => 'Ex. : Avez-vous déjà eu un chat ?'],
                'constraints' => [new Assert\Length(max: 300)],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
        $resolver->setRequired('listing');
        $resolver->setAllowedTypes('listing', AnimalListing::class);
    }
}
