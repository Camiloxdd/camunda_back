<?php

namespace App\Entity;

use App\Repository\RequisicionesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequisicionesRepository::class)]
class Requisiciones
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre_requisicion = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre_solicitante = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $fecha = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $fecha_requerido_entrega = null;

    #[ORM\Column(length: 60)]
    private ?string $tiempoAproximadoGestion = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $justificacion = null;

    #[ORM\Column(length: 100)]
    private ?string $area = null;

    #[ORM\Column(length: 100)]
    private ?string $sede = null;

    #[ORM\Column(length: 50)]
    private ?string $urgencia = null;

    #[ORM\Column]
    private ?bool $presupuestada = null;

    #[ORM\Column]
    private ?int $valor_total = null;

    #[ORM\Column(length: 100)]
    private ?string $process_instance_key = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    /**
     * @var Collection<int, RequisicionProductos>
     */
    #[ORM\OneToMany(targetEntity: RequisicionProductos::class, mappedBy: 'requisicion_id', orphanRemoval: true)]
    private Collection $requisicionProductos;

    /**
     * @var Collection<int, RequisicionAprobaciones>
     */
    #[ORM\OneToMany(targetEntity: RequisicionAprobaciones::class, mappedBy: 'requisicion_id', orphanRemoval: true)]
    private Collection $requisicionAprobaciones;

    public function __construct()
    {
        $this->requisicionProductos = new ArrayCollection();
        $this->requisicionAprobaciones = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombreRequisicion(): ?string
    {
        return $this->nombre_requisicion;
    }

    public function setNombreRequisicion(string $nombre_requisicion): static
    {
        $this->nombre_requisicion = $nombre_requisicion;

        return $this;
    }

    public function getNombreSolicitante(): ?string
    {
        return $this->nombre_solicitante;
    }

    public function setNombreSolicitante(string $nombre_solicitante): static
    {
        $this->nombre_solicitante = $nombre_solicitante;

        return $this;
    }

    public function getFecha(): ?\DateTime
    {
        return $this->fecha;
    }

    public function setFecha(\DateTime $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getFechaRequeridoEntrega(): ?\DateTime
    {
        return $this->fecha_requerido_entrega;
    }

    public function setFechaRequeridoEntrega(\DateTime $fecha_requerido_entrega): static
    {
        $this->fecha_requerido_entrega = $fecha_requerido_entrega;

        return $this;
    }

    public function getTiempoAproximadoGestion(): ?string
    {
        return $this->tiempoAproximadoGestion;
    }

    public function setTiempoAproximadoGestion(string $tiempoAproximadoGestion): static
    {
        $this->tiempoAproximadoGestion = $tiempoAproximadoGestion;

        return $this;
    }

    public function getJustificacion(): ?string
    {
        return $this->justificacion;
    }

    public function setJustificacion(string $justificacion): static
    {
        $this->justificacion = $justificacion;

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

    public function getUrgencia(): ?string
    {
        return $this->urgencia;
    }

    public function setUrgencia(string $urgencia): static
    {
        $this->urgencia = $urgencia;

        return $this;
    }

    public function isPresupuestada(): ?bool
    {
        return $this->presupuestada;
    }

    public function setPresupuestada(bool $presupuestada): static
    {
        $this->presupuestada = $presupuestada;

        return $this;
    }

    public function getValorTotal(): ?int
    {
        return $this->valor_total;
    }

    public function setValorTotal(int $valor_total): static
    {
        $this->valor_total = $valor_total;

        return $this;
    }

    public function getProcessInstanceKey(): ?string
    {
        return $this->process_instance_key;
    }

    public function setProcessInstanceKey(string $process_instance_key): static
    {
        $this->process_instance_key = $process_instance_key;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, RequisicionProductos>
     */
    public function getRequisicionProductos(): Collection
    {
        return $this->requisicionProductos;
    }

    public function addRequisicionProducto(RequisicionProductos $requisicionProducto): static
    {
        if (!$this->requisicionProductos->contains($requisicionProducto)) {
            $this->requisicionProductos->add($requisicionProducto);
            $requisicionProducto->setRequisicionId($this);
        }

        return $this;
    }

    public function removeRequisicionProducto(RequisicionProductos $requisicionProducto): static
    {
        if ($this->requisicionProductos->removeElement($requisicionProducto)) {
            // set the owning side to null (unless already changed)
            if ($requisicionProducto->getRequisicionId() === $this) {
                $requisicionProducto->setRequisicionId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RequisicionAprobaciones>
     */
    public function getRequisicionAprobaciones(): Collection
    {
        return $this->requisicionAprobaciones;
    }

    public function addRequisicionAprobacione(RequisicionAprobaciones $requisicionAprobacione): static
    {
        if (!$this->requisicionAprobaciones->contains($requisicionAprobacione)) {
            $this->requisicionAprobaciones->add($requisicionAprobacione);
            $requisicionAprobacione->setRequisicionId($this);
        }

        return $this;
    }

    public function removeRequisicionAprobacione(RequisicionAprobaciones $requisicionAprobacione): static
    {
        if ($this->requisicionAprobaciones->removeElement($requisicionAprobacione)) {
            // set the owning side to null (unless already changed)
            if ($requisicionAprobacione->getRequisicionId() === $this) {
                $requisicionAprobacione->setRequisicionId(null);
            }
        }

        return $this;
    }
}
