<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 140)]
    private ?string $repo = null;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $tech = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, enumType: RoleType::class)]
    private ?RoleType $role = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'date')]
    private ?DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $endDate = null;

    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTimeInterface $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRepo(): ?string
    {
        return $this->repo;
    }

    public function setRepo(?string $repo): void
    {
        $this->repo = $repo;
    }

    public function getTech(): array
    {
        return $this->tech;
    }

    public function setTech(array $tech): void
    {
        $this->tech = $tech;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getRole(): ?RoleType
    {
        return $this->role;
    }

    public function setRole(?RoleType $role): void
    {
        $this->role = $role;
    }

    public function setStartDate(DateTime $param): void
    {
        //date only format
        $this->startDate = $param;
    }

    public function getStartDate(): ?DateTimeInterface
    {
        return $this->startDate;
    }
}
