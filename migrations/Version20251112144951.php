<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112144951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE requisicion_aprobaciones (id INT AUTO_INCREMENT NOT NULL, requisicion_id_id INT NOT NULL, area VARCHAR(100) NOT NULL, rol_aprobador VARCHAR(100) NOT NULL, nombre_aprobador VARCHAR(100) NOT NULL, aprobado TINYINT(1) NOT NULL, fecha_aprobacion DATETIME NOT NULL, estado VARCHAR(100) NOT NULL, aprob_dic_typ TINYINT(1) NOT NULL, aprob_dic_sst TINYINT(1) NOT NULL, aprob_ger_typ TINYINT(1) NOT NULL, aprob_ger_sst TINYINT(1) NOT NULL, aprob_ger_admin TINYINT(1) NOT NULL, aprob_ger_gral TINYINT(1) NOT NULL, orden INT NOT NULL, visible TINYINT(1) NOT NULL, INDEX IDX_D9A8C5DC46D3D6B0 (requisicion_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE requisicion_productos (id INT AUTO_INCREMENT NOT NULL, requisicion_id_id INT NOT NULL, nombre VARCHAR(150) NOT NULL, cantidad INT NOT NULL, fecha_aprobado VARCHAR(100) NOT NULL, descripcion LONGTEXT NOT NULL, compra_tecnologica TINYINT(1) NOT NULL, ergonomico TINYINT(1) NOT NULL, visible TINYINT(1) NOT NULL, valor_estimado NUMERIC(10, 0) NOT NULL, centro_costo VARCHAR(100) NOT NULL, cuenta_contable VARCHAR(100) NOT NULL, aprobado VARCHAR(100) NOT NULL, INDEX IDX_2250A1CA46D3D6B0 (requisicion_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE requisiciones (id INT AUTO_INCREMENT NOT NULL, nombre_requisicion VARCHAR(255) NOT NULL, nombre_solicitante VARCHAR(255) NOT NULL, fecha DATE NOT NULL, fecha_requerido_entrega DATE NOT NULL, tiempo_aproximado_gestion VARCHAR(60) NOT NULL, justificacion LONGTEXT NOT NULL, area VARCHAR(100) NOT NULL, sede VARCHAR(100) NOT NULL, urgencia VARCHAR(50) NOT NULL, presupuestada TINYINT(1) NOT NULL, valor_total INT NOT NULL, process_instance_key VARCHAR(100) NOT NULL, status VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, correo VARCHAR(255) NOT NULL, contraseña VARCHAR(255) NOT NULL, cargo VARCHAR(255) NOT NULL, telefono VARCHAR(255) NOT NULL, area VARCHAR(255) NOT NULL, sede VARCHAR(255) NOT NULL, super_admin TINYINT(1) NOT NULL, aprobador TINYINT(1) NOT NULL, solicitante TINYINT(1) NOT NULL, comprador TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE requisicion_aprobaciones ADD CONSTRAINT FK_D9A8C5DC46D3D6B0 FOREIGN KEY (requisicion_id_id) REFERENCES requisiciones (id)');
        $this->addSql('ALTER TABLE requisicion_productos ADD CONSTRAINT FK_2250A1CA46D3D6B0 FOREIGN KEY (requisicion_id_id) REFERENCES requisiciones (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE requisicion_aprobaciones DROP FOREIGN KEY FK_D9A8C5DC46D3D6B0');
        $this->addSql('ALTER TABLE requisicion_productos DROP FOREIGN KEY FK_2250A1CA46D3D6B0');
        $this->addSql('DROP TABLE requisicion_aprobaciones');
        $this->addSql('DROP TABLE requisicion_productos');
        $this->addSql('DROP TABLE requisiciones');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
