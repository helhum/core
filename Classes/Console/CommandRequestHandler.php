<?php
namespace TYPO3\CMS\Core\Console;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command Line Interface Request Handler dealing with registered commands.
 */
class CommandRequestHandler implements RequestHandlerInterface
{
    /**
     * Instance of the current TYPO3 bootstrap
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * Instance of the symfony application
     * @var Application
     */
    protected $application;

    /**
     * @var []
     */
    protected $availableCommands;

    /**
     * Constructor handing over the bootstrap
     *
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
        $this->application = new Application('TYPO3 CMS', TYPO3_version);
    }

    /**
     * Handles any commandline request
     *
     * @param InputInterface $input
     * @return void
     */
    public function handleRequest(InputInterface $input)
    {
        $output = new ConsoleOutput();

        $this->bootstrap->loadExtensionTables();

        // Check if the command to run needs a backend user to be loaded
        $command = $this->getCommandToRun($input);
        foreach ($this->availableCommands as $data) {
            if ($data['command'] !== $command) {
                continue;
            }
            if (isset($data['user'])) {
                $this->initializeBackendUser();
            }
        }

        // Make sure output is not buffered, so command-line output and interaction can take place
        $this->bootstrap->endOutputBufferingAndCleanPreviousOutput();

        if (!$command) {
            $cliKeys = array_keys($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']);

            $output->writeln('Old entrypoint keys available:');
            asort($cliKeys);
            foreach ($cliKeys as $key => $value) {
                $output->writeln('  ' . $value);
            }
            $output->writeln('');
            $output->writeln('TYPO3 Console Commands:');
        }

        $exitCode = $this->application->run($input, $output);
        exit($exitCode);
    }

    /**
     * Logs in the user _cli_ to the system
     *
     * @throws \RuntimeException if a non-admin Backend user could not be loaded
     */
    protected function initializeBackendUser()
    {
        // create the BE_USER object
        $this->bootstrap->initializeBackendUser(CommandLineUserAuthentication::class);
        // log-in the _cli_ user, create the record if it does not exist
        /** @var CommandLineUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $backendUser->authenticate();
        $this->bootstrap
            ->initializeLanguageObject();
    }

    /**
     * This request handler can handle any CLI request
     *
     * @param InputInterface $input
     * @return bool Always TRUE
     */
    public function canHandleRequest(InputInterface $input)
    {
        $this->populateAvailableCommands();
        return true;
    }

    /**
     * Returns the priority - how eager the handler is to actually handle the request.
     *
     * @return int The priority of the request handler.
     */
    public function getPriority()
    {
        return 50;
    }

    /**
     *
     * @param InputInterface $input
     * @return bool|Command
     */
    protected function getCommandToRun(InputInterface $input)
    {
        $firstArgument = $input->getFirstArgument();
        try {
            return $this->application->find($firstArgument);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * put all available commands inside the application
     */
    protected function populateAvailableCommands()
    {
        $this->availableCommands = $this->getAvailableCommands();
        foreach ($this->availableCommands as $name => $data) {
            /** @var Command $cmd */
            $cmd = GeneralUtility::makeInstance($data['class'], $name);
            $this->application->add($cmd);
            $this->availableCommands[$name]['command'] = $cmd;
        }
    }

    /**
     * Fetches all commands registered via Commands.php of all active packages
     *
     * @return array
     */
    protected function getAvailableCommands()
    {
        /** @var PackageManager $packageManager */
        $packageManager = Bootstrap::getInstance()->getEarlyInstance(PackageManager::class);
        $availableCommands = [];

        foreach ($packageManager->getActivePackages() as $package) {
            $commandsOfExtension = $package->getPackagePath() . 'Configuration/Commands.php';
            if (@is_file($commandsOfExtension)) {
                $commands = require_once $commandsOfExtension;
                if (is_array($commands)) {
                    $availableCommands = array_merge($availableCommands, $commands);
                }
            }
        }

        return $availableCommands;
    }
}
