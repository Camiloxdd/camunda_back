<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255)]
    private ?string $correo = null;

    #[ORM\Column(length: 255)]
    private ?string $contraseña = null;

    #[ORM\Column(length: 255)]
    private ?string $cargo = null;

    #[ORM\Column(length: 255)]
    private ?string $telefono = null;

    #[ORM\Column(length: 255)]
    private ?string $area = null;

    #[ORM\Column(length: 255)]
    private ?string $sede = null;

    #[ORM\Column]
    private ?bool $super_admin = null;

    #[ORM\Column]
    private ?bool $aprobador = null;

    #[ORM\Column]
    private ?bool $solicitante = null;

    #[ORM\Column]
    private ?bool $comprador = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getCorreo(): ?string
    {
        return $this->correo;
    }

    public function setCorreo(string $correo): static
    {
        $this->correo = $correo;

        return $this;
    }

    public function getContraseña(): ?string
    {
        return $this->contraseña;
    }

    public function setContraseña(string $contraseña): static
    {
        $this->contraseña = $contraseña;

        return $this;
    }

    public function getCargo(): ?string
    {
        return $this->cargo;
    }

    public function setCargo(string $cargo): static
    {
        $this->cargo = $cargo;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(string $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getSede(): ?string
    {
        return $this->sede;
    }

    public function setSede(string $sede): static
    {
        $this->sede = $sede;

        return $this;
    }

    public function getSuperAdmin(): ?bool
    {
        return $this->super_admin;
    }

    public function setSuperAdmin(bool $super_admin): static
    {
        $this->super_admin = $super_admin;

        return $this;
    }

    public function getAprobador(): ?bool
    {
        return $this->aprobador;
    }

    public function setAprobador(bool $aprobador): static
    {
        $this->aprobador = $aprobador;

        return $this;
    }

    public function getSolicitante(): ?bool
    {
        return $this->solicitante;
    }

    public function setSolicitante(bool $solicitante): static
    {
        $this->solicitante = $solicitante;

        return $this;
    }

    public function getComprador(): ?bool
    {
        return $this->comprador;
    }

    public function setComprador(bool $comprador): static
    {
        $this->comprador = $comprador;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->correo;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->super_admin) $roles[] = 'ROLE_ADMIN';
        if ($this->aprobador) $roles[] = 'ROLE_APPROVER';
        if ($this->solicitante) $roles[] = 'ROLE_REQUESTER';
        if ($this->comprador) $roles[] = 'ROLE_BUYER';

        return array_unique($roles);
    }

    public function getPassword(): ?string
    {
        return $this->contraseña;
    }

    public function eraseCredentials(): void {}
    public function getPublicData(): array
    {

        return [
            'id' => $this->getId(),
            'nombre' => $this->getNombre(),
            'correo' => $this->getCorreo(),
            'cargo' => $this->getCargo(),
        ];
    }
}
