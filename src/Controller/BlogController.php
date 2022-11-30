<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Event\CommentCreatedEvent;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Security\PostVoter;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use http\Message;
use PHPUnit\Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Controller used to manage blog contents in the public part of the site.
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[Route('/')]
class BlogController extends AbstractController
{
    /**
     * NOTE: For standard formats, Symfony will also automatically choose the best
     * Content-Type header for the response.
     *
     * See https://symfony.com/doc/current/routing.html#special-parameters
     */
    #[Route('/', defaults: ['page' => '1', '_format' => 'html'], methods: ['GET'], name: 'blog_index')]
    #[Route('/new', defaults: ['page' => '1', '_format' => 'html'], methods: ['GET'], name: 'blog_index_2')]
    #[Route('/rss.xml', defaults: ['page' => '1', '_format' => 'xml'], methods: ['GET'], name: 'blog_rss')]
    #[Route('/page/{page<[1-9]\d*>}', defaults: ['_format' => 'html'], methods: ['GET'], name: 'blog_index_paginated')]
    #[Cache(smaxage: 10)]
    public function index(Request $request, int $page, string $_format, PostRepository $posts, TagRepository $tags): JsonResponse
    {
        $tag = null;
        //dd($request->query);
        if ($request->query->has('tag')) {
            $tag = $tags->findOneBy(['name' => $request->query->get('tag')]);
        }
        $latestPosts = null;

        if(!$latestPosts){
            $latestPosts = $posts->findNewestAll($tag);
        }

        // Every template name also has two extensions that specify the format and
        // engine for that template.
        // See https://symfony.com/doc/current/templates.html#template-naming

        $postsJson = [];
        foreach ($latestPosts as $post){
            $postsJson[] = $post->toJson();
        }
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($postsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response;

        return $this->render('blog/index.'.$_format.'.twig', [
            'paginator' => $latestPosts,
            'tagName' => $tag?->getName(),
        ]);
    }

    #[Route('/newest', defaults: ['page' => '1', '_format' => 'html'], methods: ['GET'], name: 'blog_newest_index')]
    #[Route('/rss.xml', defaults: ['page' => '1', '_format' => 'xml'], methods: ['GET'], name: 'blog_newest_rss')]
    #[Route('/page/{page<[1-9]\d*>}', defaults: ['_format' => 'html'], methods: ['GET'], name: 'blog_newest_paginated')]
    #[Cache(smaxage: 10)]
    public function newestPosts(Request $request, int $page, string $_format, PostRepository $posts, TagRepository $tags): JsonResponse
    {
        $tag = null;

        if ($request->query->has('tag')) {
            $tag = $tags->findOneBy(['name' => $request->query->get('tag')]);
        }
        $latestPosts = null;
        if ($request->query->has('type')) {
            $type = '';
            switch ($request->query->get('type')){
                case 'ask':
                    $type = 'ask';
                    //$latestPosts = $posts->findBy(['type' => $type]);
                    $latestPosts = $posts->findByTypeAll($tag, $type);
                    break;
                case 'url':
                    $type = 'url';
                    //$latestPosts = $posts->findBy(['type' => $type]);
                    $latestPosts = $posts->findByTypeAll($tag, $type);
                    break;
                default:
                    $latestPosts = $posts->findLatestAll($tag);
            }
        }
        if(!$latestPosts){
            $latestPosts = $posts->findLatestAll($tag);
        }

        $postsJson = [];
        foreach ($latestPosts as $post){
            $postsJson[] = $post->toJson();
        }
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($postsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response;
        // Every template name also has two extensions that specify the format and
        // engine for that template.
        // See https://symfony.com/doc/current/templates.html#template-naming
        //return $this->render('blog/index.'.$_format.'.twig', [
        //    'paginator' => $latestPosts,
        //    'tagName' => $tag?->getName(),
        //]);
    }

    /**
     * Creates a new Post entity.
     *
     * NOTE: the Method annotation is optional, but it's a recommended practice
     * to constraint the HTTP methods each controller responds to (by default
     * it responds to all methods).
     */
    #[Route('/posts', methods: ['POST'], name: 'post_new')]
    //#[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(Request $request, EntityManagerInterface $entityManager, PostRepository $postRepository, UserRepository $userRepository, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse
    {
        $post = new Post();
        $requestContentJson = json_decode($request->getContent());

        $trimmed = trim($requestContentJson->title);
        $trimmed = strtolower($trimmed);
        $slug = str_replace(' ', '-', $trimmed);

        $post->setContent($requestContentJson->content);
        $post->setSlug($slug);
        $post->setLink($requestContentJson->link);
        $post->setTitle($requestContentJson->title);

        $author = $userRepository->findOneBy(["id" => $requestContentJson->author_id]);
        $post->setAuthor($author);

        $response = new JsonResponse();

        if($post->getLink())
            $post->setType("url");
        else
            $post->setType("ask");

        $errors = $validator->validate($post);

        $response = new JsonResponse();
        $response->setStatusCode(200);

        if($errors->count() > 0){
            $serializerErrors = $serializer->serialize($errors, 'json', ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
            $response->setStatusCode(422);
            $response->setContent($serializerErrors);
        }
        else {
            $entityManager->persist($post);
            $entityManager->flush();

            $jsonPost = $post->toJson();
            $response->setContent(json_encode($jsonPost, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $response->setStatusCode(200);
        }
        return $response;
    }

    /**
     * NOTE: The $post controller argument is automatically injected by Symfony
     * after performing a database query looking for a Post with the 'slug'
     * value given in the route.
     *
     * See https://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/converters.html
     */
    #[Route('/posts/{slug}', methods: ['GET'], name: 'blog_post')]
    public function postShow(Post $post, CommentRepository $commentRepository): JsonResponse
    {

        $comments = new ArrayCollection($commentRepository->findBy(['post' => $post, 'parentComment' => null]));

        $post->setComments($comments);
        $postArray = $post->toJson();

        $postArray['comments'] = [];
        foreach ($comments as $comment){
            $postArray['comments'][] = $comment->toJson();

        }

        //dd($post->toJson());

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($postArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response;
        //return $this->render('blog/post_show.html.twig', ['post' => $post]);
    }

    #[Route('/posts/{slug}/vote', methods: ['GET'], name: 'vote_post')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function postVote(Post $post,  EntityManagerInterface $entityManager): Response
    {
        $post->addVote($this->getUser());
        //$post->addUserIdVotes($this->getUser()->getId());

        $entityManager->persist($post);
        $entityManager->flush();


        //dd($newPost);
        //return $this->redirectToRoute('blog_post', array('slug' => $post->getSlug()));
        return $this->redirectToRoute('blog_index');
    }

    /**
     * NOTE: The ParamConverter mapping is required because the route parameter
     * (postSlug) doesn't match any of the Doctrine entity properties (slug).
     *
     * See https://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/converters.html#doctrine-converter
     */
    #[Route('/comment/{postSlug}/new', methods: ['POST'], name: 'comment_new')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[ParamConverter('post', options: ['mapping' => ['postSlug' => 'slug']])]
    public function commentNew(Request $request, Post $post, EventDispatcherInterface $eventDispatcher, EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $comment->setAuthor($this->getUser());
        $post->addComment($comment);

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($comment);
            $entityManager->flush();

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

            return $this->redirectToRoute('blog_post', ['slug' => $post->getSlug()]);
        }

        return $this->render('blog/comment_form_error.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    /**
     * NOTE: The ParamConverter mapping is required because the route parameter
     * (postSlug) doesn't match any of the Doctrine entity properties (slug).
     *
     * See https://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle/annotations/converters.html#doctrine-converter
     */
    #[Route('/comment/{id}', methods: ['POST'], name: 'reply_new')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
//    #[ParamConverter('parentComment', options: ['mapping' => ['id' => 'id']])]
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

    /**
     * This controller is called directly via the render() function in the
     * blog/post_show.html.twig template. That's why it's not needed to define
     * a route name for it.
     *
     * The "id" of the Post is passed in and then turned into a Post object
     * automatically by the ParamConverter.
     */
    public function commentForm(Post $post): Response
    {
        $form = $this->createForm(CommentType::class);

        return $this->render('blog/_comment_form.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }
    public function replyForm(Comment $comment): Response
    {
        $form = $this->createForm(CommentType::class);

        return $this->render('blog/_reply_form.html.twig', [
            'comment' => $comment,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/search', methods: ['GET'], name: 'blog_search')]
    public function search(Request $request, PostRepository $posts): Response
    {
        $query = $request->query->get('q', '');
        $limit = $request->query->get('l', 10);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('blog/search.html.twig', ['query' => $query]);
        }

        $foundPosts = $posts->findBySearchQuery($query, $limit);

        $results = [];
        foreach ($foundPosts as $post) {
            $results[] = [
                'title' => htmlspecialchars($post->getTitle(), \ENT_COMPAT | \ENT_HTML5),
                'date' => $post->getPublishedAt()->format('M d, Y'),
                'author' => htmlspecialchars($post->getAuthor()->getFullName(), \ENT_COMPAT | \ENT_HTML5),
                'summary' => htmlspecialchars($post->getSummary(), \ENT_COMPAT | \ENT_HTML5),
                'url' => $this->generateUrl('blog_post', ['slug' => $post->getSlug()]),
            ];
        }

        return $this->json($results);
    }

    /**
     * Finds and displays a Post entity.
     */
    #[Route('/{id<\d+>}', methods: ['GET'], name: 'admin_post_show')]
    public function show(Post $post): Response
    {
        // This security check can also be performed
        // using a PHP attribute: #[IsGranted('show', subject: 'post', message: 'Posts can only be shown to their authors.')]
        $this->denyAccessUnlessGranted(PostVoter::SHOW, $post, 'Posts can only be shown to their authors.');

        return $this->render('admin/blog/show.html.twig', [
            'post' => $post,
        ]);
    }

    /**
     * Displays a form to edit an existing Post entity.
     */
    #[Route('/{id<\d+>}/edit', methods: ['GET', 'POST'], name: 'admin_post_edit')]
    #[IsGranted('edit', subject: 'post', message: 'Posts can only be edited by their authors.')]
    public function edit(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'post.updated_successfully');

            return $this->redirectToRoute('admin_post_edit', ['id' => $post->getId()]);
        }

        return $this->render('admin/blog/edit.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a Post entity.
     */
    #[Route('/{id}/delete', methods: ['POST'], name: 'admin_post_delete')]
    #[IsGranted('delete', subject: 'post')]
    public function delete(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('admin_post_index');
        }

        // Delete the tags associated with this blog post. This is done automatically
        // by Doctrine, except for SQLite (the database used in this application)
        // because foreign key support is not enabled by default in SQLite
        $post->getTags()->clear();

        $entityManager->remove($post);
        $entityManager->flush();

        $this->addFlash('success', 'post.deleted_successfully');

        return $this->redirectToRoute('admin_post_index');
    }

}
