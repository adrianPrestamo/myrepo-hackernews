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
use OpenApi\Annotations as OAA;
use OpenApi\Attributes as OA;
/**
 * Controller used to manage blog contents in the public part of the site.
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */

#[Route('/api')]
#[OA\Tag(name: 'Posts')]
class BlogController extends AbstractController
{

    #[OA\Parameter(parameter: 'tag_in_query', name: 'tag', in: 'query', description: 'The field used to filter by tag', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Returns the posts ordered by relevance'
    )]
    #[Route('/posts/new', methods: ['GET'], name: 'blog_index_2')]
    public function index(Request $request, PostRepository $posts, TagRepository $tags): JsonResponse
    {
        $tag = null;
        if ($request->query->has('tag')) {
            $tag = $tags->findOneBy(['name' => $request->query->get('tag')]);
        }
        $latestPosts = $posts->findNewestAll($tag);

        $postsJson = [];
        foreach ($latestPosts as $post){
            $postsJson[] = $post->toJson();
        }
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($postsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    #[OA\Parameter(parameter: 'tag_in_query', name: 'tag', in: 'query', description: 'The field used to filter by tag', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Returns ask posts'
    )]
    #[Route('/posts/ask', methods: ['GET'], name: 'blog_index_ask')]
    public function askPosts(Request $request, PostRepository $posts, TagRepository $tags): JsonResponse
    {
        $tag = null;
        //dd($request->query);
        if ($request->query->has('tag')) {
            $tag = $tags->findOneBy(['name' => $request->query->get('tag')]);
        }

        $latestPosts = $posts->findByTypeAll($tag, 'ask');
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
    }

    #[OA\Parameter(parameter: 'tag_in_query', name: 'tag', in: 'query', description: 'The field used to filter by tag', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Returns url posts'
    )]
    #[Route('/posts/url', methods: ['GET'], name: 'blog_index_url')]
    public function urlPosts(Request $request, PostRepository $posts, TagRepository $tags): JsonResponse
    {
        $tag = null;
        //dd($request->query);
        if ($request->query->has('tag')) {
            $tag = $tags->findOneBy(['name' => $request->query->get('tag')]);
        }

        $latestPosts = $posts->findByTypeAll($tag, 'url');
        $postsJson = [];
        foreach ($latestPosts as $post){
            $postsJson[] = $post->toJson();
        }
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($postsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    #[OA\Parameter(parameter: 'tag_in_query', name: 'tag', in: 'query', description: 'The field used to filter by tag', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Returns all posts ordered by publication time'
    )]
    #[Route('/posts/newest', methods: ['GET'], name: 'blog_newest_index')]
    public function newestPosts(Request $request, PostRepository $posts, TagRepository $tags): JsonResponse
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
     * NOTE: Creates a new Post entity.
     */
    #[Route('/posts', methods: ['POST'], name: 'post_new')]
    /**
     * @OAA\RequestBody(
     *     required=true,
     *     @OAA\JsonContent(
     *         example={
     *           "author_id": 5,
     *           "title": "ASK HN: What is your name sir?",
     *           "content": "This is a well refined post",
     *           "link": null,
     *           "published_at": "10-11-2022 14:29:51"
     *           }
     *     )
     * )
     * */
    //#[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse
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
        $post->setAuthor($this->getUser());

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
    #[OA\Parameter(parameter: 'q_in_query', name: 'q', in: 'query', description: 'The field used search', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(parameter: 'l_in_query', name: 'l', in: 'query', description: 'The field used search', schema: new OA\Schema(type: 'string'))]
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
     * Deletes a Post entity.
     */
    #[Route('/posts', methods: ['DELETE'], name: 'admin_post_delete')]
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
