<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée le schéma complet de la plateforme LeBonFoyer et supprime les tables
 * héritées de l'ancienne ébauche (pet_listing, user, adoption_application).
 */
final class Version20261101092051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial LeBonFoyer (remplace les tables de l\'ancienne ébauche).';
    }

    public function up(Schema $schema): void
    {
        // Anciennes tables de l'ébauche, incompatibles avec le nouveau schéma.
        $this->addSql('DROP TABLE IF EXISTS adoption_application');
        $this->addSql('DROP TABLE IF EXISTS pet_listing');
        $this->addSql('DROP TABLE IF EXISTS user');

        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, phone VARCHAR(30) DEFAULT NULL, type VARCHAR(255) NOT NULL, cedant_status VARCHAR(255) DEFAULT NULL, organization_name VARCHAR(180) DEFAULT NULL, siren VARCHAR(30) DEFAULT NULL, rna VARCHAR(30) DEFAULT NULL, breeding_number VARCHAR(60) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, housing_type VARCHAR(255) DEFAULT NULL, has_garden TINYINT NOT NULL, has_terrace TINYINT NOT NULL, has_other_animals TINYINT NOT NULL, has_children TINYINT NOT NULL, is_verified TINYINT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_APP_USER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE animal_listing (id INT AUTO_INCREMENT NOT NULL, species VARCHAR(255) NOT NULL, name VARCHAR(120) NOT NULL, birth_date DATE NOT NULL, birth_date_estimated TINYINT NOT NULL, identification_number VARCHAR(40) NOT NULL, description LONGTEXT NOT NULL, city VARCHAR(120) NOT NULL, postal_code VARCHAR(10) NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, price DOUBLE PRECISION DEFAULT NULL, status VARCHAR(255) NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cedant_id INT NOT NULL, INDEX IDX_LISTING_SEARCH (species, status, city), INDEX IDX_LISTING_BIRTH_DATE (birth_date), INDEX IDX_44DF27EEFCE251DC (cedant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE adoption_question (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(300) NOT NULL, position INT NOT NULL, listing_id INT DEFAULT NULL, INDEX IDX_918F97FDD4619D1A (listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE animal_photo (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, is_cover TINYINT NOT NULL, created_at DATETIME NOT NULL, listing_id INT DEFAULT NULL, INDEX IDX_35445DECD4619D1A (listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE adoption_application (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, handover_date DATE NOT NULL, warning_confirmed TINYINT NOT NULL, warning_confirmed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, decided_at DATETIME DEFAULT NULL, handed_over_at DATETIME DEFAULT NULL, listing_id INT NOT NULL, adoptant_id INT NOT NULL, INDEX IDX_22268C30D4619D1A (listing_id), INDEX IDX_22268C308D8B49F9 (adoptant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE application_answer (id INT AUTO_INCREMENT NOT NULL, answer LONGTEXT NOT NULL, application_id INT NOT NULL, question_id INT NOT NULL, INDEX IDX_6AEE46E03E030ACD (application_id), INDEX IDX_6AEE46E01E27F6BF (question_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(80) NOT NULL, subject_type VARCHAR(80) DEFAULT NULL, subject_id INT DEFAULT NULL, context JSON NOT NULL, created_at DATETIME NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_AUDIT_CREATED_AT (created_at), INDEX IDX_F6E1C0F510DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, listing_id INT DEFAULT NULL, INDEX IDX_8A8E26E9D4619D1A (listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_participant (conversation_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), PRIMARY KEY (conversation_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307FF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');

        $this->addSql('ALTER TABLE animal_listing ADD CONSTRAINT FK_44DF27EEFCE251DC FOREIGN KEY (cedant_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE adoption_question ADD CONSTRAINT FK_918F97FDD4619D1A FOREIGN KEY (listing_id) REFERENCES animal_listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE animal_photo ADD CONSTRAINT FK_35445DECD4619D1A FOREIGN KEY (listing_id) REFERENCES animal_listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE adoption_application ADD CONSTRAINT FK_22268C30D4619D1A FOREIGN KEY (listing_id) REFERENCES animal_listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE adoption_application ADD CONSTRAINT FK_22268C308D8B49F9 FOREIGN KEY (adoptant_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE application_answer ADD CONSTRAINT FK_6AEE46E03E030ACD FOREIGN KEY (application_id) REFERENCES adoption_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE application_answer ADD CONSTRAINT FK_6AEE46E01E27F6BF FOREIGN KEY (question_id) REFERENCES adoption_question (id)');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F510DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9D4619D1A FOREIGN KEY (listing_id) REFERENCES animal_listing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reset_password_request');
        $this->addSql('DROP TABLE IF EXISTS message');
        $this->addSql('DROP TABLE IF EXISTS conversation_participant');
        $this->addSql('DROP TABLE IF EXISTS conversation');
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS application_answer');
        $this->addSql('DROP TABLE IF EXISTS adoption_application');
        $this->addSql('DROP TABLE IF EXISTS animal_photo');
        $this->addSql('DROP TABLE IF EXISTS adoption_question');
        $this->addSql('DROP TABLE IF EXISTS animal_listing');
        $this->addSql('DROP TABLE IF EXISTS app_user');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
