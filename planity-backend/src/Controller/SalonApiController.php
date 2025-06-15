<?php

namespace App\Controller;

use App\Entity\Salon;
use App\Repository\SalonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SalonApiController extends AbstractController
{
    #[Route('/api/salons', name: 'api_salons_list', methods: ['GET'])]
    public function listSalonsAction(SalonRepository $salonRepository): JsonResponse
    {
        $salons = $salonRepository->findAll();
        return $this->json($salons, 200, [], ['groups' => 'salon:read']);
    }

    #[Route('/api/salons/{id}', name: 'api_salon_detail', methods: ['GET'])]
    public function getSalonAction(Salon $salon): JsonResponse // Using ParamConverter
    {
        // $salon will be null if not found and handled by Symfony's default 404 listener
        // or you can explicitly check:
        // if (!$salon) {
        //     return $this->json(['message' => 'Salon not found'], JsonResponse::HTTP_NOT_FOUND);
        // }
        return $this->json($salon, 200, [], ['groups' => 'salon:read']);
    }

    #[Route('/api/salons', name: 'api_salons_create', methods: ['POST'])]
    #[IsGranted('ROLE_SALON_OWNER')] // Or ROLE_USER, or IS_AUTHENTICATED_FULLY depending on policy
    public function createSalonAction(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
             // Should be caught by IsGranted, but as a fallback
            return $this->json(['message' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $salon = new Salon();
        $salon->setName($data['name'] ?? null);
        $salon->setAddress($data['address'] ?? null);
        $salon->setPhoneNumber($data['phoneNumber'] ?? null);
        $salon->setDescription($data['description'] ?? null);
        $salon->setOwner($user);

        $errors = $validator->validate($salon);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($salon);
        $entityManager->flush();

        return $this->json($salon, JsonResponse::HTTP_CREATED, [], ['groups' => 'salon:read']);
    }
}
