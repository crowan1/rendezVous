<?php

namespace App\Controller;

use App\Entity\Salon;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ServiceApiController extends AbstractController
{
    #[Route('/api/salons/{id}/services', name: 'api_salon_services_list', methods: ['GET'])]
    public function listSalonServicesAction(Salon $salon): Response
    {
        // The Salon object is injected thanks to the ParamConverter using the {id} from the route.
        $services = $salon->getServices();

        return $this->json($services, Response::HTTP_OK, [], ['groups' => 'service:read']);
    }

    #[Route('/api/salons/{id}/services', name: 'api_salon_services_create', methods: ['POST'])]
    public function addSalonServiceAction(
        Salon $salon,
        Request $request,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): Response {
        // Authorization: Check if the current user owns the salon
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if ($salon->getOwner() !== $currentUser) {
            // If you have specific roles like ROLE_SALON_OWNER and want to allow admins too,
            // you might use $this->isGranted('ROLE_ADMIN') or a voter.
            // For direct ownership:
            return $this->json(['message' => 'Not authorized to add services to this salon'], Response::HTTP_FORBIDDEN);
        }

        // Deserialize JSON data from request body into a new Service object
        try {
            $service = $serializer->deserialize(
                $request->getContent(),
                Service::class,
                'json',
                ['groups' => 'service:write']
            );
        } catch (\Symfony\Component\Serializer\Exception\ExceptionInterface $e) {
            return $this->json(['message' => 'Invalid JSON body: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        // Set the salon for the new service
        $service->setSalon($salon);

        // Validation
        $errors = $validator->validate($service);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Persist and flush
        $em->persist($service);
        $em->flush();

        return $this->json($service, Response::HTTP_CREATED, [], ['groups' => 'service:read']);
    }

    #[Route('/api/services/{id}', name: 'api_service_detail', methods: ['GET'])]
    public function getServiceAction(Service $service): Response
    {
        return $this->json($service, Response::HTTP_OK, [], ['groups' => 'service:read']);
    }

    #[Route('/api/services/{id}', name: 'api_service_update', methods: ['PUT'])]
    public function updateServiceAction(
        Service $service,
        Request $request,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): Response {
        // Authorization: Check if the current user owns the salon of the service
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$service->getSalon() || $service->getSalon()->getOwner() !== $currentUser) {
            return $this->json(['message' => 'Not authorized to update this service'], Response::HTTP_FORBIDDEN);
        }

        // Deserialize JSON data from request body into the existing Service object
        try {
            $serializer->deserialize(
                $request->getContent(),
                Service::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $service, 'groups' => 'service:write']
            );
        } catch (\Symfony\Component\Serializer\Exception\ExceptionInterface $e) {
            return $this->json(['message' => 'Invalid JSON body: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        // Validation
        $errors = $validator->validate($service);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json($service, Response::HTTP_OK, [], ['groups' => 'service:read']);
    }

    #[Route('/api/services/{id}', name: 'api_service_delete', methods: ['DELETE'])]
    public function deleteServiceAction(Service $service, EntityManagerInterface $em): Response
    {
        // Authorization: Check if the current user owns the salon of the service
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }
        if (!$service->getSalon() || $service->getSalon()->getOwner() !== $currentUser) {
            return $this->json(['message' => 'Not authorized to delete this service'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($service);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
