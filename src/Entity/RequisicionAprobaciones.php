<?php

namespace App\Entity;

use App\Repository\RequisicionAprobacionesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequisicionAprobacionesRepository::class)]
class RequisicionAprobaciones
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'requisicionAprobaciones')]
    #[ORM\JoinColumn(nullable: false)]
    private ?requisiciones $requisicion_id = null;

    #[ORM\Column(length: 100)]
    private ?string $area = null;

    #[ORM\Column(length: 100)]
    private ?string $rol_aprobador = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre_aprobador = null;

    #[ORM\Column]
    private ?bool $aprobado = null;

    #[ORM\Column]
    private ?\DateTime $fecha_aprobacion = null;

    #[ORM\Column(length: 100)]
    private ?string $estado = null;

    #[ORM\Column]
    private ?bool $aprob_dic_typ = null;

    #[ORM\Column]
    private ?bool $aprob_dic_sst = null;

    #[ORM\Column]
    private ?bool $aprob_ger_typ = null;

    #[ORM\Column]
    private ?bool $aprob_ger_sst = null;

    #[ORM\Column]
    private ?bool $aprob_ger_admin = null;

    #[ORM\Column]
    private ?bool $aprob_ger_gral = null;

    #[ORM\Column]
    private ?int $orden = null;

    #[ORM\Column]
    private ?bool $visible = null;

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

    public function getArea(): ?string
    {
        return $this->area;
    }

    public function setArea(string $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getRolAprobador(): ?string
    {
        return $this->rol_aprobador;
    }

    public function setRolAprobador(string $rol_aprobador): static
    {
        $this->rol_aprobador = $rol_aprobador;

        return $this;
    }

    public function getNombreAprobador(): ?string
    {
        return $this->nombre_aprobador;
    }

    public function setNombreAprobador(string $nombre_aprobador): static
    {
        $this->nombre_aprobador = $nombre_aprobador;

        return $this;
    }

    public function isAprobado(): ?bool
    {
        return $this->aprobado;
    }

    public function setAprobado(bool $aprobado): static
    {
        $this->aprobado = $aprobado;

        return $this;
    }

    public function getFechaAprobacion(): ?\DateTime
    {
        return $this->fecha_aprobacion;
    }

    public function setFechaAprobacion(\DateTime $fecha_aprobacion): static
    {
        $this->fecha_aprobacion = $fecha_aprobacion;

        return $this;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function isAprobDicTyp(): ?bool
    {
        return $this->aprob_dic_typ;
    }

    public function setAprobDicTyp(bool $aprob_dic_typ): static
    {
        $this->aprob_dic_typ = $aprob_dic_typ;

        return $this;
    }

    public function isAprobDicSst(): ?bool
    {
        return $this->aprob_dic_sst;
    }

    public function setAprobDicSst(bool $aprob_dic_sst): static
    {
        $this->aprob_dic_sst = $aprob_dic_sst;

        return $this;
    }

    public function isAprobGerTyp(): ?bool
    {
        return $this->aprob_ger_typ;
    }

    public function setAprobGerTyp(bool $aprob_ger_typ): static
    {
        $this->aprob_ger_typ = $aprob_ger_typ;

        return $this;
    }

    public function isAprobGerSst(): ?bool
    {
        return $this->aprob_ger_sst;
    }

    public function setAprobGerSst(bool $aprob_ger_sst): static
    {
        $this->aprob_ger_sst = $aprob_ger_sst;

        return $this;
    }

    public function isAprobGerAdmin(): ?bool
    {
        return $this->aprob_ger_admin;
    }

    public function setAprobGerAdmin(bool $aprob_ger_admin): static
    {
        $this->aprob_ger_admin = $aprob_ger_admin;

        return $this;
    }

    public function isAprobGerGral(): ?bool
    {
        return $this->aprob_ger_gral;
    }

    public function setAprobGerGral(bool $aprob_ger_gral): static
    {
        $this->aprob_ger_gral = $aprob_ger_gral;

        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

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
}
