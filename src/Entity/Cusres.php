<?php

namespace App\Entity;

use App\Repository\CusresRepository;
use Doctrine\ORM\Mapping as ORM;
use function strlen;

#[ORM\Entity(repositoryClass: CusresRepository::class)]
#[ORM\Table(name: 'cusres')]
#[ORM\UniqueConstraint(columns: ['slug'])]
class Cusres
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $slug;

    // arrays of IDs
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $projects = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $certifications = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $awards = [];

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $experiences = [];

    // add column "template" to store the template name
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'jakes'])]
    private string $template = 'jakes';

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        if (strlen($slug) > 10) {
            throw new \InvalidArgumentException('Slug must be <= 10 characters');
        }
        $this->slug = $slug;
        return $this;
    }

    public function getProjects(): array
    {
        return $this->projects;
    }

    public function setProjects(array $projects): self
    {
        $this->projects = $projects;
        return $this;
    }

    public function getCertifications(): array
    {
        return $this->certifications;
    }

    public function setCertifications(array $certifications): self
    {
        $this->certifications = $certifications;
        return $this;
    }

    public function getAwards(): array
    {
        return $this->awards;
    }

    public function setAwards(array $awards): self
    {
        $this->awards = $awards;
        return $this;
    }

    public function getExperiences(): array
    {
        return $this->experiences;
    }

    public function setExperiences(array $experiences): self
    {
        $this->experiences = $experiences;
        return $this;
    }
}
