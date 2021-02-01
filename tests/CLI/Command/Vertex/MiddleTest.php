<?php

/*
 * This file is part of the Geotools library.
 *
 * (c) Antoine Corcy <contact@sbin.dk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Geotools\Tests\CLI\Command\Vertex;

use League\Geotools\CLI\Application;
use League\Geotools\CLI\Command\Vertex\Middle;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Antoine Corcy <contact@sbin.dk>
 */
class MiddleTest extends \League\Geotools\Tests\TestCase
{
    protected $application;
    protected $command;
    protected $commandTester;

    protected function setup(): void
    {
        $this->application = new Application;
        $this->application->add(new Middle);

        $this->command = $this->application->find('vertex:middle');

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteWithoutArguments()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');
        $this->commandTester->execute(array(
            'command' => $this->command->getName(),
        ));
    }

    public function testExecuteInvalidArguments()
    {
        $this->expectException(\League\Geotools\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('It should be a valid and acceptable ways to write geographic coordinates !');
        $this->commandTester->execute(array(
            'command'     => $this->command->getName(),
            'origin'      => 'foo, bar',
            'destination' => ' ',
        ));
    }

    public function testExecute()
    {
        $this->commandTester->execute(array(
            'command'     => $this->command->getName(),
            'origin'      => '40° 26.7717, -79° 56.93172',
            'destination' => '30°16′57″N 029°48′32″W',
        ));

        $this->assertTrue(is_string($this->commandTester->getDisplay()));
        $this->assertRegExp('/38\.068139209793, -53\.187718854545/', $this->commandTester->getDisplay());
    }

    public function testExecuteWithEmptyEllipsoidOption()
    {
        $this->expectException(\League\Geotools\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please provide an ellipsoid name !');
        $this->commandTester->execute(array(
            'command'     => $this->command->getName(),
            'origin'      => '40° 26.7717, -79° 56.93172',
            'destination' => '30°16′57″N 029°48′32″W',
            '--ellipsoid' => ' ',
        ));
    }

    public function testExecuteWithoutAvailableEllipsoidOption()
    {
        $this->expectException(\League\Geotools\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('foo ellipsoid does not exist in selected reference ellipsoids !');
        $this->commandTester->execute(array(
            'command'     => $this->command->getName(),
            'origin'      => '40° 26.7717, -79° 56.93172',
            'destination' => '30°16′57″N 029°48′32″W',
            '--ellipsoid' => 'foo',
        ));
    }

    public function testExecuteWithEllipsoid()
    {
        $this->commandTester->execute(array(
            'command'     => $this->command->getName(),
            'origin'      => '40° 26.7717, -79° 56.93172',
            'destination' => '30°16′57″N 029°48′32″W',
            '--ellipsoid' => 'KRASSOVSKY',
        ));

        $this->assertTrue(is_string($this->commandTester->getDisplay()));
        $this->assertRegExp('/38\.068139209793, -53\.187718854545/', $this->commandTester->getDisplay());
    }
}
