<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\SalonRepository;
use Doctrine\ORM\EntityManagerInterface;

class RegistrationControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Ensure fresh database for each test - this is a simple approach
        // More sophisticated approaches might involve specific test traits or listeners
        $this->runCommand('doctrine:database:drop --force --env=test --if-exists');
        $this->runCommand('doctrine:database:create --env=test');
        $this->runCommand('doctrine:migrations:migrate --no-interaction --env=test');
    }

    protected function runCommand(string $command): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $application->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\StringInput($command);
        $application->run($input);
    }

    public function testRegisterSalonSuccess(): void
    {
        $this->client->request(
            'POST',
            '/api/register/salon',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'testowner@example.com',
                'password' => 'password123',
                'salonName' => 'Test Salon',
                'salonAddress' => '123 Test St',
                'salonPhoneNumber' => '555-0101',
                'salonDescription' => 'A salon for testing.',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201); // HTTP_CREATED

        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $responseContent);
        $this->assertEquals('Salon registered successfully!', $responseContent['message']);
        $this->assertArrayHasKey('user', $responseContent);
        $this->assertEquals('testowner@example.com', $responseContent['user']['email']);
        $this->assertContains('ROLE_SALON_OWNER', $responseContent['user']['roles']);
        $this->assertArrayHasKey('salon', $responseContent);
        $this->assertEquals('Test Salon', $responseContent['salon']['name']);

        // Verify in database
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneByEmail('testowner@example.com');
        $this->assertNotNull($user);
        $this->assertContains('ROLE_SALON_OWNER', $user->getRoles());

        $salonRepository = static::getContainer()->get(SalonRepository::class);
        $salon = $salonRepository->findOneByName('Test Salon');
        $this->assertNotNull($salon);
        $this->assertSame($user->getId(), $salon->getOwner()->getId());
    }

    public function testRegisterSalonMissingRequiredFields(): void
    {
        $this->client->request(
            'POST',
            '/api/register/salon',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'testmissing@example.com',
                // 'password' => 'password123', // Missing password
                'salonName' => 'Test Salon Missing',
                'salonAddress' => '124 Test St',
            ])
        );

        $this->assertResponseStatusCodeSame(400); // HTTP_BAD_REQUEST
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseContent);
        $this->assertStringContainsString('Missing required field: password', $responseContent['error']);
    }

    public function testRegisterSalonInvalidEmail(): void
    {
        $this->client->request(
            'POST',
            '/api/register/salon',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'notanemail',
                'password' => 'password123',
                'salonName' => 'Test Salon Invalid Email',
                'salonAddress' => '125 Test St',
            ])
        );

        $this->assertResponseStatusCodeSame(400); // HTTP_BAD_REQUEST
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $responseContent);
        // Default Symfony validator message for email might vary, adjust if needed
        $this->assertStringContainsString('email: This value is not a valid email address.', $responseContent['errors'][0]);
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        // It's good practice to close the EntityManager to prevent memory leaks in long test runs
        if ($this->entityManager !== null) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }
}
