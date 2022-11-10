<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221109235852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE symfony_demo_comment ADD replies_id INT DEFAULT NULL, DROP parent_comment_id');
        $this->addSql('ALTER TABLE symfony_demo_comment ADD CONSTRAINT FK_53AD8F837927FBC6 FOREIGN KEY (replies_id) REFERENCES symfony_demo_comment (id)');
        $this->addSql('CREATE INDEX IDX_53AD8F837927FBC6 ON symfony_demo_comment (replies_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE symfony_demo_comment DROP FOREIGN KEY FK_53AD8F837927FBC6');
        $this->addSql('DROP INDEX IDX_53AD8F837927FBC6 ON symfony_demo_comment');
        $this->addSql('ALTER TABLE symfony_demo_comment ADD parent_comment_id INT NOT NULL, DROP replies_id');
    }
}
