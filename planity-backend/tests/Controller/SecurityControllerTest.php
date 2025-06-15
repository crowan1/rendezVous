<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Ensure fresh database
        $this->runCommand('doctrine:database:drop --force --env=test --if-exists');
        $this->runCommand('doctrine:database:create --env=test');
        $this->runCommand('doctrine:migrations:migrate --no-interaction --env=test');

        // Create a test user
        $user = new User();
        $user->setEmail('testlogin@example.com');
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    protected function runCommand(string $command): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $application->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\StringInput($command);
        $application->run($input);
    }

    public function testLoginSuccess(): void
    {
        // Symfony's form_login expects form parameters
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        // Extract CSRF token
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => 'testlogin@example.com',
            '_password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/'); // Default redirect for form_login success is often the homepage or configured target
        $this->client->followRedirect();

        // Try accessing a protected route
        $this->client->request('GET', '/api/me');
        $this->assertResponseIsSuccessful();
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('testlogin@example.com', $responseContent['email']);
    }

    public function testLoginFailure(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => 'testlogin@example.com',
            '_password' => 'wrongpassword',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/login'); // Should redirect back to login on failure
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger'); // Default Symfony login form shows error in this
    }

    public function testLogout(): void
    {
        // First, log in
        $crawler = $this->client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $this->client->request('POST', '/login', [
            '_username' => 'testlogin@example.com',
            '_password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();


        // Access logout path
        $this->client->request('GET', '/logout');
        // Default logout target is often the homepage, or it might be configured
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();

        // Attempt to access protected route
        $this->client->request('GET', '/api/me');
        // For form_login, this usually redirects to the login page (302)
        // If it were a pure API (like json_login not redirecting), it would be 401.
        $this->assertResponseStatusCodeSame(302);
        $this->assertTrue($this->client->getResponse()->isRedirect('/login'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->entityManager !== null) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }
}
