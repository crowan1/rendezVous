<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\UserRepository; // Required if you fetch user directly

class UserApiControllerTest extends WebTestCase
{
    private $client;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->runCommand('doctrine:database:drop --force --env=test --if-exists');
        $this->runCommand('doctrine:database:create --env=test');
        $this->runCommand('doctrine:migrations:migrate --no-interaction --env=test');

        // Create a test user
        $user = new User();
        $user->setEmail('apitestuser@example.com');
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER', 'ROLE_TESTER']); // Add a custom role for verification
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

    public function testGetMeUnauthenticated(): void
    {
        $this->client->request('GET', '/api/me');
        // For form_login setup, unauthenticated access to a firewalled path typically redirects.
        // If using #[IsGranted] on a controller not covered by a stateful firewall rule (e.g. API token auth),
        // it might return 401 directly. With form_login, it redirects to login.
        $this->assertResponseStatusCodeSame(302); // Redirects to login
        $this->assertTrue($this->client->getResponse()->isRedirect('/login'));
    }

    public function testGetMeAuthenticated(): void
    {
        // Log in the user first (using form login)
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful("Failed to load login page. Status: " . $this->client->getResponse()->getStatusCode());

        $csrfTokenInput = $crawler->filter('input[name="_csrf_token"]');
        if ($csrfTokenInput->count() === 0) {
            $this->fail("CSRF token not found on login page. HTML: " . $crawler->html());
        }
        $csrfToken = $csrfTokenInput->attr('value');

        $this->client->request('POST', '/login', [
            '_username' => 'apitestuser@example.com',
            '_password' => 'password123',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect(), "Login failed or did not redirect. Status: " . $this->client->getResponse()->getStatusCode() . " Location: " . $this->client->getResponse()->headers->get('Location'));
        $this->client->followRedirect(); // Follow redirect to wherever it goes (e.g. '/')

        // Now access /api/me
        $this->client->request('GET', '/api/me');
        $this->assertResponseIsSuccessful("Failed to access /api/me after login. Status: " . $this->client->getResponse()->getStatusCode() . " Content: " . $this->client->getResponse()->getContent());

        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotNull($responseContent, "Response from /api/me is not valid JSON.");
        $this->assertEquals('apitestuser@example.com', $responseContent['email']);
        $this->assertContains('ROLE_USER', $responseContent['roles']);
        $this->assertContains('ROLE_TESTER', $responseContent['roles']);
        $this->assertArrayHasKey('id', $responseContent);
        $this->assertArrayNotHasKey('password', $responseContent, "Password field should not be exposed.");
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
