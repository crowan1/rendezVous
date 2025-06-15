<?php

namespace App\Controller;

use App\Entity\Salon;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/api/register/salon', name: 'api_salon_register', methods: ['POST'])]
    public function registerSalon(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Basic validation for required fields
        $requiredFields = ['email', 'password', 'salonName', 'salonAddress'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => sprintf('Missing required field: %s', $field)], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        // Create User
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles(['ROLE_USER', 'ROLE_SALON_OWNER']);

        // Create Salon
        $salon = new Salon();
        $salon->setName($data['salonName']);
        $salon->setAddress($data['salonAddress']);
        $salon->setPhoneNumber($data['salonPhoneNumber'] ?? null);
        $salon->setDescription($data['salonDescription'] ?? null);
        $salon->setOwner($user);

        // Validate User and Salon
        $userErrors = $validator->validate($user);
        if (count($userErrors) > 0) {
            $errorMessages = [];
            foreach ($userErrors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $salonErrors = $validator->validate($salon);
        if (count($salonErrors) > 0) {
            $errorMessages = [];
            foreach ($salonErrors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Persist entities
        $entityManager->persist($user);
        $entityManager->persist($salon);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Salon registered successfully!',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
            'salon' => [
                'id' => $salon->getId(),
                'name' => $salon->getName(),
                'address' => $salon->getAddress(),
                'phoneNumber' => $salon->getPhoneNumber(),
                'description' => $salon->getDescription(),
                'ownerId' => $salon->getOwner()->getId(),
            ]
        ], JsonResponse::HTTP_CREATED);
    }
}
