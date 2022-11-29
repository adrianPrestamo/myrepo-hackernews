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

/**
 * Controller used to manage current user.
 *
 * @author Romain Monteil <monteil.romain@gmail.com>
 */
#[Route('/profile')]
class UserController extends AbstractController
{
    #[Route('/show/{username}', methods: ['GET'], name: 'user_show')]
//    #[ParamConverter('user', options: ['mapping' => ['username' => 'username']])]
    public function show(Request $request, UserRepository $userRepository, PostRepository $postRepository, CommentRepository $commentRepository, EntityManagerInterface $entityManager): JsonResponse
    {

        $user = $userRepository->findOneBy(["username" => $request->attributes->get("username")]);
        $posts = $postRepository->findOneBy(["author" => $user]);
        //TODO add comments
        //$comments = $commentRepository->findOneBy(["author" => $user]);
        //$user->comments = $comments;
        $user->posts = $posts;

        $postsJson = [];
        foreach ($posts as $post){
            $postsJson[] = $post->toJson();
        }
        $userJson = $user->toJson();
        $userJson['posts'] = $postsJson;

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setContent(json_encode($userJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response;

        return $this->render('user/show.html.twig', [
            'showUser' => $user
        ]);
    }

    #[Route('/edit', methods: ['GET', 'POST'], name: 'user_edit')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('user_edit');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/change-password', methods: ['GET', 'POST'], name: 'user_change_password')]
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
}
