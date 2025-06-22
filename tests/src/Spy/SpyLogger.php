<?php

namespace Drupal\Tests\taxonomy_section_paths\Spy;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class SpyLogger implements LoggerInterface {

  public array $records = [];

  public function emergency($message, array $context = []): void {
    $this->log(LogLevel::EMERGENCY, $message, $context);
  }

  public function alert($message, array $context = []): void {
    $this->log(LogLevel::ALERT, $message, $context);
  }

  public function critical($message, array $context = []): void {
    $this->log(LogLevel::CRITICAL, $message, $context);
  }

  public function error($message, array $context = []): void {
    $this->log(LogLevel::ERROR, $message, $context);
  }

  public function warning($message, array $context = []): void {
    $this->log(LogLevel::WARNING, $message, $context);
  }

  public function notice($message, array $context = []): void {
    $this->log(LogLevel::NOTICE, $message, $context);
  }

  public function info($message, array $context = []): void {
    $this->log(LogLevel::INFO, $message, $context);
  }

  public function debug($message, array $context = []): void {
    $this->log(LogLevel::DEBUG, $message, $context);
  }

  public function log($level, $message, array $context = []): void {
    //~ print('/////////////////');
    //~ print($message);
    //~ print('/////////////////');
    $this->records[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
  }
}


