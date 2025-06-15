<?php

namespace App\Tests\Entity;

use App\Entity\Salon;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class SalonTest extends TestCase
{
    public function testCreateSalon(): void
    {
        $salon = new Salon();
        $this->assertInstanceOf(Salon::class, $salon);
        $this->assertNull($salon->getId());
    }

    public function testGetSetName(): void
    {
        $salon = new Salon();
        $name = 'Glamour Nails';
        $salon->setName($name);
        $this->assertSame($name, $salon->getName());
    }

    public function testGetSetAddress(): void
    {
        $salon = new Salon();
        $address = '123 Main St, Anytown, USA';
        $salon->setAddress($address);
        $this->assertSame($address, $salon->getAddress());
    }

    public function testGetSetPhoneNumber(): void
    {
        $salon = new Salon();
        $phoneNumber = '555-1234';
        $salon->setPhoneNumber($phoneNumber);
        $this->assertSame($phoneNumber, $salon->getPhoneNumber());

        $salon->setPhoneNumber(null);
        $this->assertNull($salon->getPhoneNumber());
    }

    public function testGetSetDescription(): void
    {
        $salon = new Salon();
        $description = 'A wonderful salon experience.';
        $salon->setDescription($description);
        $this->assertSame($description, $salon->getDescription());

        $salon->setDescription(null);
        $this->assertNull($salon->getDescription());
    }

    public function testGetSetOwner(): void
    {
        $salon = new Salon();
        $owner = new User();
        $owner->setEmail('owner@example.com'); // Assuming User has setEmail

        $salon->setOwner($owner);
        $this->assertSame($owner, $salon->getOwner());
    }
}
