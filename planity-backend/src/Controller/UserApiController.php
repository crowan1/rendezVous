<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserApiController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getMeAction(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            // This check is theoretically redundant due to #[IsGranted]
            // but can be kept for absolute clarity or if #[IsGranted] were removed.
            return $this->json(['message' => 'Authentication required'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json($user, 200, [], ['groups' => 'user:read']);
    }
}
