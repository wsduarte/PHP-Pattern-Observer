<?php
/**
* Observer-SPL-PHP-Pattern
*
* Copyright (c) 2010, Julien Pauli <jpauli@php.net>.
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions
* are met:
*
* * Redistributions of source code must retain the above copyright
* notice, this list of conditions and the following disclaimer.
*
* * Redistributions in binary form must reproduce the above copyright
* notice, this list of conditions and the following disclaimer in
* the documentation and/or other materials provided with the
* distribution.
*
* * Neither the name of Julien Pauli nor the names of his
* contributors may be used to endorse or promote products derived
* from this software without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
* "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
* LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
* FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
* COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
* INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
* BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
* LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
* CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
* LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
* ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* @package Observer
* @author Julien Pauli <jpauli@php.net>
* @copyright 2010 Julien Pauli <jpauli@php.net>
* @license http://www.opensource.org/licenses/bsd-license.php BSD License
*/
namespace Observer;

/**
* Base class for error handling.

* This class may not be perfect but is a very good implementation
* of the Subject/Observer pattern in PHP.
*
* @package Observer
* @author Julien Pauli <jpauli@php.net>
* @copyright 2010 Julien Pauli <jpauli@php.net>
* @license http://www.opensource.org/licenses/bsd-license.php BSD License
* @version Release: @package_version@
*/
final class ErrorHandler implements Pattern\Subject, \IteratorAggregate, \Countable
{
    /**
     * Singleton instance
     *
     * @var ErrorHandler
     */
    private static $instance;

    /**
     * @var array
     */
    private $error = array();

    /**
     * Wether or not fallback to PHP internal
     * error handler
     *
     * @var bool
     */
    private $fallBackToPHPErrorHandler = false;

    /**
     * Wether or not rethrow the PHP Error
     *
     * @var bool
     */
    private $rethrowException = true;

    /**
     * Weither or not clear the last error after
     * sending it to Listeners
     *
     * @var bool
     */
    private $clearErrorAfterSending = true;

    /**
     * Should ErrorHandler catch its listeners exception
     * while dispatching them ?
     *
     * @var bool
     */
    private $catchListenersException = true;

    /**
     * Listeners classes namespace
     *
     * @var string
     */
    const LISTENERS_NS = "Listeners";

    /**
     * @var SplObjectStorage
     */
    private $observers;

    /**
     * Retrieves singleton instance
     *
     * @return ErrorHandler
     */
    public static function getInstance($andStart = false)
    {
        if (self::$instance == null) {
            self::$instance = new self;
            if ($andStart) {
                self::$instance->start();
            }
        }
        return self::$instance;
    }

    /**
     * Singleton : can't be cloned
     */
    private function __clone() { }

    /**
     * Singleton constructor
     */
    private function __construct()
    {
        $this->observers = new \SplObjectStorage();
    }

    /**
     * Factory to build some Listeners
     *
     * @param string $listener
     * @param array $args
     * @return object|false
     */
    public static function factory($listener, array $args = array())
    {
        $class = __NAMESPACE__ . "\\" . self::LISTENERS_NS . "\\" . $listener;
        try {
            $reflect = new \ReflectionClass($class);
            return $reflect->newInstanceArgs($args);
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Method run by PHP's error handler
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param array $errctx
     * @return bool
     */
    public function __invoke($errno, $errstr, $errfile = '', $errline = 0, array $errctx = [])
    {
        if(error_reporting() == 0) { // @ errors ignored
            return;
        }
        $this->error = array($errno, $errstr, $errfile, $errline);
        $this->notify();
        if ($this->fallBackToPHPErrorHandler) {
            return false;
        }
    }

    /**
     * Method run by PHP's exception handler
     * @param \Throwable $e
     */
    public function exceptionHandler(\Throwable $e)
    {
        if ($e instanceof \Error) {
            $this($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            if ($this->rethrowException) {
                goto rethrow;
            }
        }
rethrow:
        throw $e;
    }

    /**
     * Wether or not fallback to PHP internal
     * error handler
     *
     * @param bool $bool
     * @return ErrorHandler
     */
    public function setFallBackToPHPErrorHandler($bool)
    {
        $this->fallBackToPHPErrorHandler = (bool)$bool;
        return $this;
    }

    /**
     * Wether or not fallback to PHP internal
     * error handler
     *
     * @return bool
     */
    public function getRethrowException()
    {
        return $this->rethrowException;
    }

    /**
     * Wether or not rethrow PHP error
     *
     * @param bool $bool
     * @return ErrorHandler
     */
    public function setRethrowException($bool)
    {
        $this->rethrowException = (bool)$bool;
        return $this;
    }

    /**
     * Wether or not rethrow PHP Error
     *
     * @return bool
     */
    public function getFallBackToPHPErrorHandler()
    {
        return $this->fallBackToPHPErrorHandler;
    }

    /**
     * Wether or not clear the last error after
     * sending it to Listeners
     *
     * @param bool $bool
     * @return ErrorHandler
     */
    public function setClearErrorAfterSending($bool)
    {
        $this->clearErrorAfterSending = (bool)$bool;
        return $this;
    }

    /**
     * Wether or not clear the last error after
     * sending it to Listeners
     *
     * @return bool
     */
    public function getClearErrorAfterSending()
    {
        return $this->clearErrorAfterSending;
    }

    /**
     * Wether or not the ErrorHandler should catch its
     * listeners' exceptions while notifying() them
     *
     * @param bool $bool
     * @return ErrorHandler
     */
    public function setCatchListenersException($bool)
    {
        $this->catchListenersException = (bool)$bool;
        return $this;
    }

    /**
     * Wether or not the ErrorHandler should catch its
     * listeners' exceptions while notifying() them
     *
     * @return bool
     */
    public function getCatchListenersException()
    {
        return $this->catchListenersException;
    }

    /**
     * Starts the ErrorHandler
     *
     * @return ErrorHandler
     */
    public function start()
    {
        set_error_handler($this);
        set_exception_handler([$this, 'exceptionHandler']);

        return $this;
    }

    /**
     * Stops the ErrorHandler
     *
     * @return ErrorHandler
     */
    public function stop()
    {
        restore_error_handler();
        set_exception_handler(null);

        return $this;
    }

    /**
     * Observer pattern : shared method
     * to all observers
     *
     * @return string
     */
    public function getError()
    {
        if (!$this->error) {
            return false;
        }
        return vsprintf("Error %d: %s, in file %s at line %d", $this->error);
    }

    /**
     * Resets the singleton instance
     *
     * @param bool $andRecreate
     * @return ErrorHandler|void
     */
    public static function resetInstance($andRecreate = false)
    {
        self::$instance = null;
        return $andRecreate ? self::getInstance() : null;
    }

    /**
     * Observer pattern : attaches observers
     *
     * @param Pattern\Observer $obs
     * @return ErrorHandler
     */
    public function attach(Pattern\Observer ...$obs)
    {
        foreach ($obs as $o) {
            $this->observers->attach($o);
        }
        return $this;
    }

    /**
     * Observer pattern : detaches observers
     *
     * @param Pattern\Observer $obs
     * @return ErrorHandler
     */
    public function detach(Pattern\Observer ...$obs)
    {
        foreach ($obs as $o) {
            $this->observers->detach($o);
        }
        return $this;
    }

    /**
     * Observer pattern : notify observers
     *
     * @param Pattern\Observer $obs
     * @return int
     */
    public function notify()
    {
        $i = 0;
        foreach ($this as $observer) {
            try {
                $observer->update($this);
                $i++;
            } catch(\Exception $e) {
                if (!$this->catchListenersException) {
                    throw new ErrorHandlerException("Exception while notifying observer", null, $e);
                }
            } finally {
                if ($this->clearErrorAfterSending) {
                    $this->error = array();
                }
            }
        }

        if ($this->clearErrorAfterSending) {
            $this->error = array();
        }

        return $i;
    }

    /**
     * IteratorAggregate
     *
     * @return \Iterator
     */
    public function getIterator()
    {
        return $this->observers;
    }

    /**
     * Countable
     *
     * @return int
     */
    public function count()
    {
        return count($this->observers);
    }

    /**
     * Hack for 1.attach('Listener')
     *          2.detach('Listener')
     *
     * @param string $funct
     * @param array $args
     * @return ErrorHandler
     * @throws \BadMethodCallException
     */
    public function __call($funct, $args)
    {
        if (preg_match('#(?P<prefix>at|de)tach(?P<listener>\w+)#', $funct, $matches)) {
            $meth     = $matches['prefix'] . 'tach';
            $listener = ucfirst(strtolower($matches['listener']));
            return $this->$meth(self::factory($listener, $args));
        }
        throw new \BadMethodCallException("unknown method $funct");
    }
}
