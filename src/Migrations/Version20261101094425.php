<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stocke les dimensions des photos pour éviter les décalages de mise en page (CLS).
 */
final class Version20261101094425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la largeur et la hauteur des photos d’annonces.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animal_photo ADD width INT DEFAULT NULL, ADD height INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE animal_photo DROP width, DROP height');
    }
}
