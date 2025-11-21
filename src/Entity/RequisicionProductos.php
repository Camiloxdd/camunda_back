<?php

namespace App\Entity;

use App\Repository\RequisicionProductosRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequisicionProductosRepository::class)]
class RequisicionProductos
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'requisicionProductos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?requisiciones $requisicion_id = null;

    #[ORM\Column(length: 150)]
    private ?string $nombre = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\Column(length: 100)]
    private ?string $fecha_aprobado = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descripcion = null;

    #[ORM\Column]
    private ?bool $compra_tecnologica = null;

    #[ORM\Column]
    private ?bool $ergonomico = null;

    #[ORM\Column]
    private ?bool $visible = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 0)]
    private ?string $valor_estimado = null;

    #[ORM\Column(length: 100)]
    private ?string $centro_costo = null;

    #[ORM\Column(length: 100)]
    private ?string $cuenta_contable = null;

    #[ORM\Column(length: 100)]
    private ?string $aprobado = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequisicionId(): ?requisiciones
    {
        return $this->requisicion_id;
    }

    public function setRequisicionId(?requisiciones $requisicion_id): static
    {
        $this->requisicion_id = $requisicion_id;

        return $this;
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

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(int $cantidad): static
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function getFechaAprobado(): ?string
    {
        return $this->fecha_aprobado;
    }

    public function setFechaAprobado(string $fecha_aprobado): static
    {
        $this->fecha_aprobado = $fecha_aprobado;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    public function isCompraTecnologica(): ?bool
    {
        return $this->compra_tecnologica;
    }

    public function setCompraTecnologica(bool $compra_tecnologica): static
    {
        $this->compra_tecnologica = $compra_tecnologica;

        return $this;
    }

    public function isErgonomico(): ?bool
    {
        return $this->ergonomico;
    }

    public function setErgonomico(bool $ergonomico): static
    {
        $this->ergonomico = $ergonomico;

        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function getValorEstimado(): ?string
    {
        return $this->valor_estimado;
    }

    public function setValorEstimado(string $valor_estimado): static
    {
        $this->valor_estimado = $valor_estimado;

        return $this;
    }

    public function getCentroCosto(): ?string
    {
        return $this->centro_costo;
    }

    public function setCentroCosto(string $centro_costo): static
    {
        $this->centro_costo = $centro_costo;

        return $this;
    }

    public function getCuentaContable(): ?string
    {
        return $this->cuenta_contable;
    }

    public function setCuentaContable(string $cuenta_contable): static
    {
        $this->cuenta_contable = $cuenta_contable;

        return $this;
    }

    public function getAprobado(): ?string
    {
        return $this->aprobado;
    }

    public function setAprobado(string $aprobado): static
    {
        $this->aprobado = $aprobado;

        return $this;
    }
}
