<?php
namespace TYPO3\CMS\Core\Error;

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
use TYPO3\CMS\Core\Controller\ErrorPageController;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A quite exception handler which catches but ignores any exception.
 *
 * This file is a backport from FLOW3
 */
class ProductionExceptionHandler extends AbstractExceptionHandler
{
    /**
     * Default title for error messages
     *
     * @var string
     */
    protected $defaultTitle = 'Oops, an error occurred!';

    /**
     * Default message for error messages
     *
     * @var string
     */
    protected $defaultMessage = '';

    /**
     * Constructs this exception handler - registers itself as the default exception handler.
     */
    public function __construct()
    {
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * Echoes an exception for the web.
     *
     * @param \Throwable $exception The throwable object.
     * @return void
     */
    public function echoExceptionWeb(\Throwable $exception)
    {
        $this->sendStatusHeaders($exception);
        $this->writeLogEntries($exception, self::CONTEXT_WEB);
        echo GeneralUtility::makeInstance(ErrorPageController::class)->errorAction(
            $this->getTitle($exception),
            $this->getMessage($exception),
            AbstractMessage::ERROR,
            $this->discloseExceptionInformation($exception) ? $exception->getCode() : 0
        );
    }

    /**
     * Echoes an exception for the command line.
     *
     * @param \Throwable $exception The throwable object.
     * @return void
     */
    public function echoExceptionCLI(\Throwable $exception)
    {
        $filePathAndName = $exception->getFile();
        $exceptionCodeNumber = $exception->getCode() > 0 ? '#' . $exception->getCode() . ': ' : '';
        $this->writeLogEntries($exception, self::CONTEXT_CLI);
        echo LF . 'Uncaught TYPO3 Exception ' . $exceptionCodeNumber . $exception->getMessage() . LF;
        echo 'thrown in file ' . $filePathAndName . LF;
        echo 'in line ' . $exception->getLine() . LF . LF;
        die(1);
    }

    /**
     * Determines, whether Exception details should be outputted
     *
     * @param \Throwable $exception The throwable object.
     * @return bool
     */
    protected function discloseExceptionInformation(\Throwable $exception)
    {
        // Allow message to be shown in production mode if the exception is about
        // trusted host configuration.  By doing so we do not disclose
        // any valuable information to an attacker but avoid confusions among TYPO3 admins
        // in production context.
        if ($exception->getCode() === 1396795884) {
            return true;
        }
        // Show client error messages 40x in every case
        if ($exception instanceof Http\AbstractClientErrorException) {
            return true;
        }
        // Only show errors in FE, if a BE user is authenticated
        if (TYPO3_MODE === 'FE') {
            return $GLOBALS['TSFE']->beUserLogin;
        }
        return true;
    }

    /**
     * Returns the title for the error message
     *
     * @param \Throwable $exception The throwable object.
     * @return string
     */
    protected function getTitle(\Throwable $exception)
    {
        if ($this->discloseExceptionInformation($exception) && method_exists($exception, 'getTitle') && $exception->getTitle() !== '') {
            return $exception->getTitle();
        } else {
            return $this->defaultTitle;
        }
    }

    /**
     * Returns the message for the error message
     *
     * @param \Throwable $exception The throwable object.
     * @return string
     */
    protected function getMessage(\Throwable $exception)
    {
        if ($this->discloseExceptionInformation($exception)) {
            return $exception->getMessage();
        } else {
            return $this->defaultMessage;
        }
    }
}
