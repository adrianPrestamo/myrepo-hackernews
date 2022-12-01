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

use App\Form\Type\ChangePasswordType;
use App\Form\UserType;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use OpenApi\Annotations as OAA;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Controller used to manage current user.
 *
 * @author Romain Monteil <monteil.romain@gmail.com>
 */
#[Route('/api/users')]
#[OA\Tag(name: 'Users')]
class UserController extends AbstractController
{
    #[Route('/{username}', methods: ['GET'], name: 'user_show')]
    public function show(Request $request, UserRepository $userRepository, PostRepository $postRepository, CommentRepository $commentRepository, EntityManagerInterface $entityManager): JsonResponse
    {

        $user = $userRepository->findOneBy(["username" => $request->attributes->get("username")]);
        $posts = $postRepository->findBy(["author" => $user]);
        $user->posts = $posts;
        $postsJson = [];
        foreach ($posts as $post){
            $postsJson[] = ["id" => $post->getId(),"slug" => $post->getSlug()];
        }
        $userJson = $user->toJson();
        $userJson['posts'] = $postsJson;

        $userComments = $commentRepository->findBy(['author' => $user]);
        $userCommentsJson = [];
        foreach ($userComments as $userComment){
            $userCommentsJson[] = $userComment->toJson();
        }

        $userJson['comments'] = $userCommentsJson;

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($userJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    #[Route('/{id}', methods: ['PUT'], name: 'user_edit')]
    /**
     * @OAA\RequestBody(
     *     required=true,
     *     @OAA\JsonContent(
     *         example={
     *           "about": "My name is holla!",
     *           "fullName": "Holla y addios"
     *           }
     *     )
     * )
     * */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        $jsonContent = json_decode($request->getContent());
        try{
            $user->setAbout($jsonContent->about);
            $user->setFullName($jsonContent->fullName);
        }
        catch (\Exception $e){

        }
        $entityManager->persist($user);
        $entityManager->flush();
//        dd($user);
        $response = new JsonResponse();

        $errors = $validator->validate($user);
        if($errors->count() > 0){
            $serializerErrors = $serializer->serialize($errors, 'json', ['json_encode_options' => JSON_UNESCAPED_SLASHES]);
            $response->setStatusCode(422);
            $response->setContent($serializerErrors);
        }
        $response->setStatusCode(200);
        $response->setContent($serializer->serialize($user->toJson(),'json'));

        return $response;
    }

    #[Route('', methods: ['POST'], name: 'user_change_password')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('newPassword')->getData()));
            $entityManager->flush();

            return $this->redirectToRoute('security_logout');
        }

        return $this->render('user/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{userId}/comments', methods: ['GET'], name: 'comment_index')]
    public function index(CommentRepository $comments): JsonResponse
    {

        $test = $comments->findByPost($this->getUser());
        dd($test);

        return $this->redirectTo('blog_index');
    }

}
