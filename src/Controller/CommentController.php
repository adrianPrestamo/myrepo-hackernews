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
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function replyNew(Request $request, Comment $parentComment, EventDispatcherInterface $eventDispatcher, EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $comment->setAuthor($this->getUser());
        $comment->setPost($parentComment->getPost());

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        $parentComment->addReply($comment);

        $entityManager->persist($comment);
        $entityManager->flush();
        if ($form->isSubmitted() && $form->isValid()) {
//            $entityManager->persist($comment);
//            $entityManager->flush();

            // When an event is dispatched, Symfony notifies it to all the listeners
            // and subscribers registered to it. Listeners can modify the information
            // passed in the event and they can even modify the execution flow, so
            // there's no guarantee that the rest of this controller will be executed.
            // See https://symfony.com/doc/current/components/event_dispatcher.html
            try{
                $eventDispatcher->dispatch(new CommentCreatedEvent($comment));

            }
            catch(Exception $e){

            }

            return $this->redirectToRoute('blog_post', ['slug' => $parentComment->getPost()->getSlug()]);
        }

        return $this->render('blog/comment_form_error.html.twig', [
            'post' => $parentComment->getPost(),
            'form' => $form->createView(),
        ]);
    }

}