<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Salon;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;

class UserTest extends TestCase
{
    public function testCreateUser(): void
    {
        $user = new User();
        $this->assertInstanceOf(User::class, $user);
        $this->assertNull($user->getId());
        $this->assertEmpty($user->getSalons());
        $this->assertInstanceOf(ArrayCollection::class, $user->getSalons());
    }

    public function testGetSetEmail(): void
    {
        $user = new User();
        $email = 'test@example.com';
        $user->setEmail($email);
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($email, $user->getUserIdentifier());
    }

    public function testGetSetRoles(): void
    {
        $user = new User();
        $roles = ['ROLE_ADMIN', 'ROLE_USER'];
        $user->setRoles($roles);
        // The getRoles method should always add ROLE_USER
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());

        $user->setRoles(['ROLE_TEST']);
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertContains('ROLE_TEST', $user->getRoles());
    }

    public function testGetSetPassword(): void
    {
        $user = new User();
        $password = 'hashedpassword';
        $user->setPassword($password);
        $this->assertSame($password, $user->getPassword());
    }

    public function testAddRemoveSalon(): void
    {
        $user = new User();
        $salon1 = new Salon();
        $salon1->setName('Salon Alpha'); // Assuming Salon has setName

        $user->addSalon($salon1);
        $this->assertCount(1, $user->getSalons());
        $this->assertTrue($user->getSalons()->contains($salon1));
        $this->assertSame($user, $salon1->getOwner());

        $user->removeSalon($salon1);
        $this->assertCount(0, $user->getSalons());
        $this->assertFalse($user->getSalons()->contains($salon1));
        $this->assertNull($salon1->getOwner()); // Check if owner is set to null

        // Test removing a salon that is not associated
        $salon2 = new Salon();
        $user->removeSalon($salon2); // Should not throw error and count remains 0
        $this->assertCount(0, $user->getSalons());
    }

    public function testEraseCredentials(): void
    {
        $user = new User();
        // This method is expected to be empty by default with make:user
        // If it had logic (e.g. clearing a plainPassword property), test that here
        $user->eraseCredentials();
        $this->expectNotToPerformAssertions(); // Or assert specific behavior if implemented
    }
}
