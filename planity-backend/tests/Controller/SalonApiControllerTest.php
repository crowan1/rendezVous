<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\Salon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\UserRepository;
use App\Repository\SalonRepository;

class SalonApiControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $entityManager;
    private ?User $salonOwnerUser;
    private ?User $regularUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->runCommand('doctrine:database:drop --force --env=test --if-exists');
        $this->runCommand('doctrine:database:create --env=test');
        $this->runCommand('doctrine:migrations:migrate --no-interaction --env=test');

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Create a salon owner user
        $this->salonOwnerUser = new User();
        $this->salonOwnerUser->setEmail('salonowner@example.com');
        $this->salonOwnerUser->setPassword($passwordHasher->hashPassword($this->salonOwnerUser, 'password123'));
        $this->salonOwnerUser->setRoles(['ROLE_USER', 'ROLE_SALON_OWNER']);
        $this->entityManager->persist($this->salonOwnerUser);

        // Create a regular user
        $this->regularUser = new User();
        $this->regularUser->setEmail('regularuser@example.com');
        $this->regularUser->setPassword($passwordHasher->hashPassword($this->regularUser, 'password123'));
        $this->regularUser->setRoles(['ROLE_USER']);
        $this->entityManager->persist($this->regularUser);

        $this->entityManager->flush();
    }

    protected function runCommand(string $command): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $application->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\StringInput($command);
        $application->run($input);
    }

    private function loginUser(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful("Login page failed to load for $email");
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => $email,
            '_password' => $password,
            '_csrf_token' => $csrfToken,
        ]);
        $this->assertTrue($this->client->getResponse()->isRedirect(), "Login failed for $email");
        $this->client->followRedirect();
    }

    public function testCreateSalonSuccessAsOwner(): void
    {
        $this->loginUser('salonowner@example.com', 'password123');

        $this->client->request(
            'POST',
            '/api/salons',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Owner Created Salon',
                'address' => '789 Owner Ave',
                'phoneNumber' => '555-0303',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Owner Created Salon', $responseContent['name']);
        $this->assertEquals($this->salonOwnerUser->getId(), $responseContent['owner']['id']);

        $salonRepo = static::getContainer()->get(SalonRepository::class);
        $salon = $salonRepo->findOneBy(['name' => 'Owner Created Salon']);
        $this->assertNotNull($salon);
        $this->assertEquals($this->salonOwnerUser->getId(), $salon->getOwner()->getId());
    }

    public function testCreateSalonForbiddenAsRegularUser(): void
    {
        $this->loginUser('regularuser@example.com', 'password123');

        $this->client->request(
            'POST',
            '/api/salons',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'User Salon Fail', 'address' => 'Should Not Work'])
        );
        $this->assertResponseStatusCodeSame(403); // Forbidden
    }

    public function testCreateSalonUnauthenticated(): void
    {
        $this->client->request(
            'POST',
            '/api/salons',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Unauth Salon Fail', 'address' => 'Should Not Work'])
        );
        // Expect redirect to login for form_login
        $this->assertResponseStatusCodeSame(302);
        $this->assertTrue($this->client->getResponse()->isRedirect('/login'));
    }

    public function testCreateSalonInvalidData(): void
    {
        $this->loginUser('salonowner@example.com', 'password123');
        $this->client->request(
            'POST',
            '/api/salons',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => null, 'address' => '123 Valid St']) // Name is required
        );
        $this->assertResponseStatusCodeSame(400);
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $responseContent);
        // Default message for NotNull constraint
        $this->assertStringContainsString('name: This value should not be null.', $responseContent['errors'][0]);
    }


    public function testListSalons(): void
    {
        // Create some salons
        $salon1 = new Salon();
        $salon1->setName('Test Salon Alpha');
        $salon1->setAddress('123 Alpha St');
        $salon1->setOwner($this->salonOwnerUser);
        $this->entityManager->persist($salon1);

        $salon2 = new Salon();
        $salon2->setName('Test Salon Beta');
        $salon2->setAddress('456 Beta St');
        $salon2->setOwner($this->regularUser); // Different owner
        $this->entityManager->persist($salon2);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/salons');
        $this->assertResponseIsSuccessful();
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $responseContent);
        $this->assertEquals('Test Salon Alpha', $responseContent[0]['name']);
        $this->assertEquals($this->salonOwnerUser->getEmail(), $responseContent[0]['owner']['email']);
        $this->assertEquals('Test Salon Beta', $responseContent[1]['name']);
        $this->assertEquals($this->regularUser->getEmail(), $responseContent[1]['owner']['email']);
    }

    public function testGetSalonDetail(): void
    {
        $salon = new Salon();
        $salon->setName('Detail Salon');
        $salon->setAddress('789 Detail Rd');
        $salon->setOwner($this->salonOwnerUser);
        $this->entityManager->persist($salon);
        $this->entityManager->flush();
        $salonId = $salon->getId();

        $this->client->request('GET', '/api/salons/' . $salonId);
        $this->assertResponseIsSuccessful();
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Detail Salon', $responseContent['name']);
        $this->assertEquals($this->salonOwnerUser->getEmail(), $responseContent['owner']['email']);
    }

    public function testGetSalonDetailNotFound(): void
    {
        $this->client->request('GET', '/api/salons/99999'); // Non-existent ID
        $this->assertResponseStatusCodeSame(404);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->entityManager !== null) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
        $this->salonOwnerUser = null;
        $this->regularUser = null;
    }
}
