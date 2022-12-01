<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Event\CommentCreatedEvent;
use App\Form\CommentType;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use OpenApi\Annotations as OAA;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
class CommentController extends AbstractController
{

    #[Route('/posts/{postSlug}/comments', methods: ['POST'], name: 'comment_new')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[ParamConverter('post', options: ['mapping' => ['postSlug' => 'slug']])]
    #[OA\Tag(name: 'Comments')]
    /**
     * @OAA\RequestBody(
     *     required=true,
     *     @OAA\JsonContent(
     *         example={
     *           "content": "this is a comment!"
     *           }
     *     )
     * )
     * */
    public function commentNew(Request $request, Post $post, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $comment = new Comment();
        $jsonContent = json_decode($request->getContent());
        $comment->setAuthor($this->getUser());
        $comment->setContent($jsonContent->content);

        $post->addComment($comment);

        $entityManager->persist($comment);
        $entityManager->flush();

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent($serializer->serialize($comment->toJson(),'json'));

        return $response;
    }

    #[Route('/comments/{id}', methods: ['POST'], name: 'reply_new')]
    #[OA\Tag(name: 'Comments')]
    /**
     * @OAA\RequestBody(
     *     required=true,
     *     @OAA\JsonContent(
     *         example={
     *           "content": "this is a reply to a comment!"
     *           }
     *     )
     * )
     * */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
//    #[ParamConverter('parentComment', options: ['mapping' => ['id' => 'id']])]
    public function replyNew(Request $request, Comment $parentComment, EventDispatcherInterface $eventDispatcher, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $comment = new Comment();
        $comment->setContent(json_decode($request->getContent())->content);
        $comment->setAuthor($this->getUser());
        $comment->setPost($parentComment->getPost());

        $parentComment->addReply($comment);

        $entityManager->persist($comment);
        $entityManager->flush();

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent($serializer->serialize($comment->toJson(),'json'));

        return $response;
    }
}