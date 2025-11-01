<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    //one to many with project
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $projects;

    //one to one with education
    #[ORM\OneToOne(targetEntity: Education::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Education $education = null;

    //one to many with certification
    #[ORM\OneToMany(targetEntity: Certification::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $certifications;

    //many to many with award
    #[ORM\OneToMany(targetEntity: Award::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $awards;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    public function getAwards(): Collection
    {
        return $this->awards;
    }

    public function addAward(Award $award): self
    {
        if (!$this->awards->contains($award)) {
            $this->awards[] = $award;
            $award->setUser($this);
            $this->setAwardsCount($this->getAwardsCount() + 1);
        }

        return $this;
    }

    public function removeAward(Award $award): self
    {
        if ($this->awards->removeElement($award)) {
            if ($award->getUser() === $this) {
                $award->setUser(null);
                $this->setAwardsCount($this->getAwardsCount() - 1);
            }
        }

        return $this;
    }

    public function getCertifications(): Collection
    {
        return $this->certifications;
    }

    public function addCertification(Certification $certification): self
    {
        if (!$this->certifications->contains($certification)) {
            $this->certifications[] = $certification;
            $certification->setUser($this);
        }

        return $this;
    }

    public function removeCertification(Certification $certification): self
    {
        if ($this->certifications->removeElement($certification)) {
            if ($certification->getUser() === $this) {
                $certification->setUser(null);
            }
        }

        return $this;
    }

    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setUser($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            if ($project->getUser() === $this) {
                $project->setUser(null);
            }
        }

        return $this;
    }

    public function getEducation(): ?Education
    {
        return $this->education;
    }

    public function setEducation(?Education $education): void
    {
        $this->education = $education;
    }

    #[ORM\Column(name: 'githubId', type: 'bigint')]
    private ?int $githubId = null;

    #[ORM\Column(name: 'username', type: 'string', length: 128)]
    private ?string $username = null;

    #[ORM\Column(name: 'email', type: 'string')]
    private ?string $email = null;

    #[ORM\Column(name: 'avatarUrl', type: 'string')]
    private ?string $avatarUrl = null;

    #[ORM\Column(name: 'accessToken', type: 'text')]
    private ?string $accessToken = null;

    #[ORM\Column(name: 'firstName', type: 'string', length: 128, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'lastName', type: 'string', length: 128, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'linkedin', type: 'string', length: 255, nullable: true)]
    private ?string $linkedin = null;

    #[ORM\Column(name: 'portfolio', type: 'string', nullable: true)]
    private ?string $portfolio = null;

    #[ORM\Column(name: 'leetcode', type: 'string', length: 255, nullable: true)]
    private ?string $leetcode = null;

    #[ORM\Column(name: 'skills', type: 'json', options: ['default' => '[]'])]
    private array $skills = [];

    #[ORM\Column(name: 'projectsCount', type: 'integer', options: ['default' => 0])]
    private int $projectsCount = 0;

    #[ORM\Column(name: 'certCount', type: 'integer', options: ['default' => 0])]
    private int $certCount = 0;

    #[ORM\Column(name: 'awardsCount', type: 'integer', options: ['default' => 0])]
    private int $awardsCount = 0;

    #[ORM\Column(name: 'experienceCount', type: 'integer', options: ['default' => 0])]
    private int $experienceCount = 0;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'free'])]
    private string $plan = 'free';

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): self
    {
        if (!in_array($plan, ['free', 'basic', 'pro'])) {
            throw new \InvalidArgumentException("Invalid plan type");
        }
        $this->plan = $plan;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getLinkedin(): ?string
    {
        return $this->linkedin;
    }

    public function setLinkedin(?string $linkedin): void
    {
        $this->linkedin = $linkedin;
    }

    public function getLeetcode(): ?string
    {
        return $this->leetcode;
    }

    public function setLeetcode(?string $leetcode): void
    {
        $this->leetcode = $leetcode;
    }

    public function getSkills(): array
    {
        return $this->skills;
    }

    public function setSkills(array $skills): void
    {
        $this->skills = $skills;
    }

    public function incrementSkill(string $tech): self
    {
        $this->skills[$tech] = ($this->skills[$tech] ?? 0) + 1;
        return $this;
    }

    public function decrementSkill(string $tech): self
    {
        if (isset($this->skills[$tech])) {
            $this->skills[$tech]--;
            if ($this->skills[$tech] <= 0) {
                unset($this->skills[$tech]);
            }
        }
        return $this;
    }

    public function getProjectsCount(): int
    {
        return $this->projectsCount;
    }

    public function setProjectsCount(int $projectsCount): void
    {
        $this->projectsCount = $projectsCount;
    }
    public function incrementProjectsCount(): void
    {
        $this->projectsCount++;
    }
    public function decrementProjectsCount(): void
    {
        if ($this->projectsCount > 0) {
            $this->projectsCount--;
        }
    }

    public function getCertCount(): int
    {
        return $this->certCount;
    }

    public function setCertCount(int $certCount): void
    {
        $this->certCount = $certCount;
    }

    public function incrementCertCount(): void
    {
        $this->certCount++;
    }

    public function decrementCertCount(): void
    {
        if ($this->certCount > 0) {
            $this->certCount--;
        }
    }

    public function getAwardsCount(): int
    {
        return $this->awardsCount;
    }

    public function setAwardsCount(int $awardsCount): void
    {

        $this->awardsCount = $awardsCount < 0 ? 0 : $awardsCount;
    }

    public function getExperienceCount(): int
    {
        return $this->experienceCount;
    }

    public function setExperienceCount(int $experienceCount): void
    {
        $this->experienceCount = $experienceCount;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getGithubId(): ?int
    {
        return $this->githubId;
    }

    public function setGithubId(?int $githubId): void
    {
        $this->githubId = $githubId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): void
    {
        $this->avatarUrl = $avatarUrl;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getPortfolio(): ?string
    {
        return $this->portfolio;
    }

    public function setPortfolio(?string $portfolio): void
    {
        $this->portfolio = $portfolio;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->id;
    }
}
