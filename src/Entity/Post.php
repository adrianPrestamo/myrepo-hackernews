<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use OpenApi\Attributes as OA;

/**
 * Defines the properties of the Post entity to represent the blog posts.
 *
 * See https://symfony.com/doc/current/doctrine.html#creating-an-entity-class
 *
 * Tip: if you have an existing database, you can generate these entity class automatically.
 * See https://symfony.com/doc/current/doctrine/reverse_engineering.html
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'symfony_demo_post')]
#[UniqueEntity(fields: ['slug'], errorPath: 'title', message: 'post.slug_unique')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["default", "create", "update"])]
    #[OA\Property(example: 1)]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank]
    #[Groups(["default", "create", "update"])]
    #[OA\Property(example: 'Hello World!')]
    private ?string $title = null;

    #[ORM\Column(type: 'string')]
    #[Groups(["default", "create", "update"])]
    #[OA\Property(example: 'hello-world')]
    private ?string $slug = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $summary = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'post.blank_content')]
    #[Assert\Length(min: 10, minMessage: 'post.too_short_content')]
    #[OA\Property(example: 'Im saluting everyone!')]
    private ?string $content = null;

    #[ORM\Column(type: 'datetime')]
    #[OA\Property(example: '21-12-2022 22:00:00')]
    private \DateTime $publishedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[OA\Property(example: 1)]
    private ?User $author = null;

    /**
     * @var Comment[]|Collection
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['publishedAt' => 'DESC'])]
    private Collection $comments;

    /**
     * @var Tag[]|Collection
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'symfony_demo_post_tag')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Assert\Count(max: 4, maxMessage: 'post.too_many_tags')]
    private Collection $tags;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    #[OA\Property(example: null)]
    private ?string $link = null;

    #[ORM\Column(length: 255)]
    #[OA\Property(example: 'ask')]
    private ?string $type = 'ask';

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'votedPosts')]
    private Collection $votes;

    #[ORM\Column(type: 'json')]
    #[OA\Property(example: [2,4])]
    private array $userIdVotes = [];

    #[ORM\Column(options: ["default" => 0])]
    #[OA\Property(example: 2)]
    private ?int $numberOfVotes = 0;

    public function __construct()
    {
        $this->publishedAt = new \DateTime();
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getPublishedAt(): \DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTime $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): void
    {
        $comment->setPost($this);
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

    public function removeComment(Comment $comment): void
    {
        $this->comments->removeElement($comment);
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary;
    }

    public function addTag(Tag ...$tags): void
    {
        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }
    }

    public function removeTag(Tag $tag): voidlink
    {
        $this->tags->removeElement($tag);
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function setComments(ArrayCollection $comments)
    {
        $this->comments = $comments;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(User $vote): self
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $this->addUserIdVotes($vote->getId());
            $this->incrementNumberOfVotes();

        }

        return $this;
    }

    public function removeVote(User $vote): self
    {
        $this->votes->removeElement($vote);

        return $this;
    }

    public function getUserIdVotes(): array
    {
        $userVotedIds = $this->userIdVotes;

        // guarantees that a user always has at least one role for security
        if (empty($userVotedIds)) {
            $userVotedIds[] = [];
        }

        return array_unique($userVotedIds);

        return $this->userIdVotes;
    }

    public function setUserIdVotes(array $userIdVotes): self
    {
        $this->userIdVotes = $userIdVotes;

        return $this;
    }
    public function addUserIdVotes(string $userId){
        $this->userIdVotes[] = $userId;
        $this->userIdVotes = array_unique($this->userIdVotes);

        //$this->userIdVotes = array_push($this->userIdVotes, $userId);
    }

    public function getNumberOfVotes(): ?int
    {
        return $this->numberOfVotes;
    }

    public function setNumberOfVotes(int $numberOfVotes): self
    {
        $this->numberOfVotes = $numberOfVotes;

        return $this;
    }
    public function incrementNumberOfVotes(): self
    {
        $this->numberOfVotes += 1;

        return $this;
    }

    public function toJson(){

        $array['id'] = $this->id;
        $array['author_id'] = $this->author->getId();
        $array['title'] = $this->title;
        $array['content'] = $this->content;
        $array['slug'] = $this->slug;
        $array['type'] = $this->type;
        $array['content'] = $this->content;
        $array['link'] = $this->link;
        $array['votes'] = $this->numberOfVotes;
        $array['user_id_votes'] = $this->userIdVotes;

        foreach ($this->tags as $tag){
            $array['tags'][] = [$tag->getId(), $tag->getName()];
        }
        $array['published_at'] = $this->publishedAt->format("d-m-Y H:i:s");

        return $array;
    }
}
